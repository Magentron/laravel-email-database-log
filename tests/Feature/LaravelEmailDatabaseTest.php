<?php

namespace ShvetsGroup\LaravelEmailDatabaseLog\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use ShvetsGroup\LaravelEmailDatabaseLog\EmailLogger;
use ShvetsGroup\LaravelEmailDatabaseLog\Tests\Mail\TestMail;
use ShvetsGroup\LaravelEmailDatabaseLog\Tests\Mail\TestMailWithAttachment;
use ShvetsGroup\LaravelEmailDatabaseLog\Tests\TestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage as SymfonySentMessage;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Encoder\Base64Encoder;

class LaravelEmailDatabaseTest extends TestCase
{
    use RefreshDatabase;

    protected EmailLogger $emailLogger;

    public function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $this->emailLogger = resolve(EmailLogger::class);
    }

    #[Test]
    public function the_email_is_logged_to_the_database(): void
    {
        Mail::to('email@example.com')
            ->send(new TestMail());

        $now = now()->format('Y-m-d H:i:s');
        $this->assertDatabaseHas('email_log', [
            'date'        => $now,
            'from'        => 'Example <hello@example.com>',
            'to'          => 'email@example.com',
            'cc'          => null,
            'bcc'         => null,
            'subject'     => 'The e-mail subject',
            'body'        => '<p>Some random string.</p>',
            'attachments' => null,
            'sent_at'     => $now,

            ['message_id', '!=', null],
        ]);
    }

    #[Test]
    public function multiple_recipients_are_comma_separated(): void
    {
        Mail::to(['email@example.com', 'email2@example.com'])
            ->send(new TestMail());

        $this->assertDatabaseHas('email_log', [
            'date' => now()->format('Y-m-d H:i:s'),
            'to'   => 'email@example.com, email2@example.com',
            'cc'   => null,
            'bcc'  => null,
        ]);
    }

    #[Test]
    public function recipient_with_name_is_correctly_formatted(): void
    {
        Mail::to((object)['email' => 'email@example.com', 'name' => 'John Do'])
            ->send(new TestMail());

        $this->assertDatabaseHas('email_log', [
            'date' => now()->format('Y-m-d H:i:s'),
            'to'   => 'John Do <email@example.com>',
            'cc'   => null,
            'bcc'  => null,
        ]);
    }

    #[Test]
    public function cc_recipient_with_name_is_correctly_formatted(): void
    {
        Mail::cc((object)['email' => 'email@example.com', 'name' => 'John Do'])
            ->send(new TestMail());

        $this->assertDatabaseHas('email_log', [
            'date' => now()->format('Y-m-d H:i:s'),
            'to'   => null,
            'cc'   => 'John Do <email@example.com>',
            'bcc'  => null,
        ]);
    }

    #[Test]
    public function bcc_recipient_with_name_is_correctly_formatted(): void
    {
        Mail::bcc((object)['email' => 'email@example.com', 'name' => 'John Do'])
            ->send(new TestMail());

        $this->assertDatabaseHas('email_log', [
            'date' => now()->format('Y-m-d H:i:s'),
            'to'   => null,
            'cc'   => null,
            'bcc'  => 'John Do <email@example.com>',
        ]);
    }

    #[Test]
    public function message_id_is_filtered_from_hash(): void
    {
        $email = $this->buildEmailToSend();
        $hash1 = $this->getHashForEmail($email);

        $sentMessage = $this->buildSentMessage($email);
        $hash2       = $this->getHashForEmail($sentMessage);

        $this->assertEquals($hash1, $hash2);
    }

    #[Test]
    public function sent_message_only_is_logged(): void
    {
        $sentMessage = $this->buildSentMessage();
        $event       = new MessageSent($sentMessage);
        $this->emailLogger->handle($event);

        $now = now()->format('Y-m-d H:i:s');
        $this->assertDatabaseHas('email_log', [
            'date'    => $now,
            'from'    => 'Example <hello@example.com>',
            'to'      => 'John Do <email@example.com>',
            'cc'      => null,
            'bcc'     => 'Jane Do <jane@example.com>',
            'sent_at' => $now,

            ['message_id', '!=', null],
        ]);
    }

    #[Test]
    public function attachement_is_saved(): void
    {
        Mail::to('email@example.com')->send(new TestMailWithAttachment());

        $log = DB::table('email_log')->first();

        // TODO: Is there a beter way to tests this ?
        $encoded = (new Base64Encoder())->encodeString(file_get_contents(__DIR__ . '/../stubs/demo.txt'));

        $this->assertStringContainsString('Content-Type: text/plain; name=demo.txt', $log->attachments);
        $this->assertStringContainsString('Content-Transfer-Encoding: base64', $log->attachments);
        $this->assertStringContainsString('Content-Disposition: attachment; name=demo.txt; filename=demo.txt', $log->attachments);
        $this->assertStringContainsString($encoded, $log->attachments);
    }

    /**
     * Build an email to send.
     *
     * @return Email
     */
    protected function buildEmailToSend(): Email
    {
        $email = new Email();
        $email->from('Example <hello@example.com>')
              ->to('John Do <email@example.com>')
              ->bcc('Jane Do <jane@example.com>')
              ->subject('Just an email')
              ->html('<p>Just some content.</p>');

        return $email;
    }

    /**
     * Build a sent message, optionally from an existing email.
     *
     * @param  Email|null  $email
     * @return SentMessage
     */
    protected function buildSentMessage(Email $email = null): SentMessage
    {
        if (null === $email) {
            $email = $this->buildEmailToSend();
        }

        // add Message ID header to sent message
        $sender     = $email->getFrom()[0];
        $recipients = $email->getTo();

        $headers = $email->getHeaders();
        $headers->addHeader('X-Message-ID', '1234567890');
        $email->setHeaders($headers);

        $sentMessage = new SymfonySentMessage($email, new Envelope($sender, $recipients));

        return new SentMessage($sentMessage, new Envelope($sender, $recipients));
    }

    /**
     * Calculate the hash for the email used to identify the email in the database.
     *
     * @param  Email|SentMessage $email
     * @return string
     */
    protected function getHashForEmail(Email|SentMessage $email): string
    {
        $method = new ReflectionMethod($this->emailLogger, 'buildMessageData');
        $method->setAccessible(true);
        $data = $method->invoke($this->emailLogger, $email);

        $method = new ReflectionMethod($this->emailLogger, 'hashMessageData');
        $method->setAccessible(true);

        return $method->invoke($this->emailLogger, $data);
    }
}

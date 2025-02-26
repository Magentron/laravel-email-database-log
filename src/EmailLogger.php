<?php

namespace ShvetsGroup\LaravelEmailDatabaseLog;

use Carbon\Carbon;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\Part\DataPart;

class EmailLogger
{
    /**
     * Handle the actual logging.
     *
     * @param  MessageSending $event
     * @return void
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function handle(MessageSending|MessageSent $event): void
    {
        /** @psalm-suppress RedundantCondition */
        if ($event instanceof MessageSending) {
            $this->insertMessageSending($event->message);
        } else {
            /** @psalm-suppress UndefinedPropertyFetch */
            $this->updateMessageSent($event->sent);
        }
    }

    /**
     * Format address strings for sender, to, cc, bcc.
     *
     * @param  Email       $message
     * @param  string      $field
     * @return string|null
     */
    public function formatAddressField(Email|SentMessage $message, string $field): ?string
    {
        $headers = $this->getMessageHeaders($message);

        return $headers->get($field)?->getBodyAsString();
    }

    /**
     * Insert a message about to be sent.
     *
     * @param  Email $message
     * @return void
     */
    protected function insertMessageSending(Email $message): void
    {
        // determine the data to insert, including a hash for the message content
        $hashData         = $this->buildMessageData($message);
        $hash             = $this->hashMessageData($hashData);
        $hashData['hash'] = $hash;

        DB::table('email_log')->insert($hashData);
    }

    /**
     * Update a message after having been sent.
     *
     * @param  SentMessage $message
     * @return void
     */
    protected function updateMessageSent(SentMessage $message): void
    {
        $hashData = $this->buildMessageData($message);
        $hash     = $this->hashMessageData($hashData);

        // first find the last message that matches the hash within the last 24 hours
        $query = DB::table('email_log')->where('hash', $hash)
                                                 ->whereNull('message_id')
                                                 ->where('date', '>=', Carbon::now()->subDays(1))
                                                 ->orderBy('id')
                                                 ->limit(1);

        // if no such record found, insert a new one
        $emailLog = $query->first();
        if (null === $emailLog) {
            $this->insertMessageSending($message->getOriginalMessage());

            $emailLog = $query->first();
        }

        // update the record with the message ID and sent_at
        $updates = [
            'message_id' => $message->getMessageId(),
            'sent_at'    => Carbon::now(),
        ];

        DB::table('email_log')->where('id', $emailLog->id)
                                     ->update($updates);
    }

    /**
     * Build an array of data to insert for the message, which is also used to calculate the message hash.
     *
     * @param  Email|SentMessage $message
     * @return array
     */
    protected function buildMessageData(Email|SentMessage $message): array
    {
        $message = $this->getMessageMessage($message);

        return [
            'date'        => Carbon::now()->format('Y-m-d H:i:s'),
            'from'        => $this->formatAddressField($message, 'From'),
            'to'          => $this->formatAddressField($message, 'To'),
            'cc'          => $this->formatAddressField($message, 'Cc'),
            'bcc'         => $this->formatAddressField($message, 'Bcc'),
            'subject'     => $message->getSubject(),
            'body'        => $message->getBody()->bodyToString(),
            'headers'     => $message->getHeaders()->toString(),
            'attachments' => $this->saveAttachments($message),
        ];
    }

    /**
     * Retrieve the actual message from the message of the event.
     *
     * @param  Email|SentMessage $message
     * @return Headers
     */
    protected function getMessageHeaders(Email|SentMessage $message): Headers
    {
        return $this->getMessageMessage($message)->getHeaders();
    }

    /**
     * Retrieve the actual message from the message of the event.
     *
     * @param  Email|SentMessage $message
     * @return Email
     */
    protected function getMessageMessage(Email|SentMessage $message): Email
    {
        return $message instanceof Email ? $message : $message->getOriginalMessage();
    }

    /**
     * Determine the hash for the data of a message.
     *
     * @param  array  $hashData
     * @return string
     */
    protected function hashMessageData(array $hashData): string
    {
        return sha1(serialize($hashData));
    }

    /**
     * Collect all attachments and format them as strings.
     *
     * @param  Email|SentMessage $message
     * @return string|null
     */
    protected function saveAttachments(Email|SentMessage $message): ?string
    {
        if (empty($message->getAttachments())) {
            return null;
        }

        return collect($message->getAttachments())
            ->map(fn (DataPart $part) => $part->toString())
            ->implode("\n\n");
    }
}

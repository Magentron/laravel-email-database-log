<?php

namespace ShvetsGroup\LaravelEmailDatabaseLog\Tests\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TestMailWithAttachment extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct()
    {

    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this
            ->subject('The e-mail subject')
            ->attach(__DIR__ . '/../stubs/demo.txt')
            ->html('<p>Some random string.</p>');
    }
}

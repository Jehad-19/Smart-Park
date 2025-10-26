<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public $otp;

    public function __construct($otp)
    {
        $this->otp = $otp;
    }

    public function build()
    {
        return $this->subject('رمز التحقق الخاص بك - سمارت بارك')
            ->view('emails.otp'); // ستحتاج لإنشاء هذا الـ view
    }
}

// resources/views/emails/otp.blade.php
// <h1>رمز التحقق الخاص بك هو: {{ $otp }}</h1>

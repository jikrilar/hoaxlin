<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Tests\TestCase;

class MailConfigurationTest extends TestCase
{
    public function test_gmail_smtp_settings_build_a_transport_without_sending_mail(): void
    {
        config([
            'mail.mailers.smtp.scheme' => null,
            'mail.mailers.smtp.url' => null,
            'mail.mailers.smtp.host' => 'smtp.gmail.com',
            'mail.mailers.smtp.port' => 587,
            'mail.mailers.smtp.username' => null,
            'mail.mailers.smtp.password' => null,
        ]);

        $transport = Mail::mailer('smtp')->getSymfonyTransport();

        $this->assertInstanceOf(EsmtpTransport::class, $transport);
        $this->assertTrue($transport->isAutoTls());
        $this->assertInstanceOf(SocketStream::class, $transport->getStream());
        $this->assertSame('smtp.gmail.com', $transport->getStream()->getHost());
        $this->assertSame(587, $transport->getStream()->getPort());
    }

    public function test_docker_passes_mail_settings_to_all_laravel_services(): void
    {
        $compose = file_get_contents(base_path('docker-compose.yml'));

        $this->assertStringContainsString('environment: &laravel-environment', $compose);
        $this->assertSame(2, substr_count($compose, 'environment: *laravel-environment'));

        foreach (['MAIL_MAILER', 'MAIL_SCHEME', 'MAIL_URL', 'MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_EHLO_DOMAIN', 'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME'] as $name) {
            $this->assertMatchesRegularExpression('/^      '.preg_quote($name, '/').': "\$\{'.$name.':-[^}]*\}"$/m', $compose);
        }

        $this->assertStringContainsString('MAIL_MAILER: "${MAIL_MAILER:-log}"', $compose);
        $this->assertStringContainsString('MAIL_USERNAME: "${MAIL_USERNAME:-}"', $compose);
        $this->assertStringContainsString('MAIL_PASSWORD: "${MAIL_PASSWORD:-}"', $compose);
    }

    public function test_mail_templates_keep_log_as_default_without_credentials(): void
    {
        foreach (['.env.example', '.env.docker.example'] as $template) {
            $contents = file_get_contents(base_path($template));

            $this->assertMatchesRegularExpression('/^MAIL_MAILER=log$/m', $contents);
            $this->assertMatchesRegularExpression('/^MAIL_USERNAME=(?:null)?$/m', $contents);
            $this->assertMatchesRegularExpression('/^MAIL_PASSWORD=(?:null)?$/m', $contents);
        }
    }
}

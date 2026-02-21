<?php

namespace App\Notification\Provider;

use App\Notification\AbstractNotificationProvider;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AutoconfigureTag('app.notification_provider')]
class EmailNotificationProvider extends AbstractNotificationProvider
{
    public function __construct(
        LoggerInterface $logger,
        private readonly MailerInterface $mailer,
        #[Autowire(env: 'MAIL_FROM_EMAIL')]
        private readonly string $fromEmail = 'noreply@zigzag.com',
        #[Autowire(env: 'MAIL_FROM_NAME')]
        private readonly string $fromName = 'ZigZag School Transportation',
    ) {
        parent::__construct($logger);
    }

    public function getName(): string
    {
        return 'email';
    }

    public function send(string $recipient, string $subject, string $message, array $data = []): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $email = (new Email())
                ->from(sprintf('%s <%s>', $this->fromName, $this->fromEmail))
                ->to($recipient)
                ->subject($subject)
                ->html($this->formatMessage($message, $data));

            $this->mailer->send($email);
            $this->logNotification($recipient, $subject, true);

            return true;
        } catch (TransportExceptionInterface $e) {
            $this->logError($recipient, $e->getMessage());
            return false;
        }
    }

    private function formatMessage(string $message, array $data): string
    {
        $html = '<html><body>';
        $html .= '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
        $html .= '<h2 style="color: #2c3e50;">ZigZag School Transportation</h2>';
        $html .= '<div style="background-color: #f8f9fa; padding: 20px; border-radius: 5px;">';
        $html .= nl2br(htmlspecialchars($message));
        $html .= '</div>';

        if (!empty($data)) {
            $html .= '<div style="margin-top: 20px; padding: 15px; background-color: #e9ecef; border-radius: 5px;">';
            $html .= '<h3 style="color: #495057; margin-top: 0;">Additional Information</h3>';
            $html .= '<table style="width: 100%;">';
            foreach ($data as $key => $value) {
                $html .= sprintf(
                    '<tr><td style="padding: 5px; font-weight: bold;">%s:</td><td style="padding: 5px;">%s</td></tr>',
                    ucfirst(str_replace('_', ' ', $key)),
                    htmlspecialchars((string) $value)
                );
            }
            $html .= '</table>';
            $html .= '</div>';
        }

        $html .= '<div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #dee2e6; color: #6c757d; font-size: 12px;">';
        $html .= '<p>This is an automated notification from ZigZag School Transportation System.</p>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</body></html>';

        return $html;
    }
}

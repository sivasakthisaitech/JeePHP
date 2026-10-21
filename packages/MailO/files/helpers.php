// JeePHP package: MailO
if (!function_exists('send_mail')) {
    function send_mail(string $to, string $subject, string $view, array $data = []): bool
    {
        return \JeePHP\Mail\Mailer::sendView($to, $subject, $view, $data);
    }
}

<?php
class EmailRenderer {

    private $templatePath;

    public function __construct($templatePath = __DIR__ . '/../email-templates/') {
        $this->templatePath = rtrim($templatePath, '/') . '/';
    }

    // Load template file
    private function loadTemplate($file) {
        $filepath = $this->templatePath . $file;
        if (!file_exists($filepath)) {
            return "TEMPLATE NOT FOUND: $file";
        }
        return file_get_contents($filepath);
    }

    // Replace placeholders in {{key}} format
    private function applyData($html, $data) {
        foreach ($data as $key => $value) {
            $html = str_replace('{{'.$key.'}}', $value, $html);
        }
        return $html;
    }

    // Main function
    public function render($templateCode, $data = []) {

        // Map template codes to filenames + subjects
        $map = [

            // Registration
            'REG_STUDENT_COMPLETE' => [
                'file' => 'registration-student.html',
                'subject' => 'Welcome to ACA – Your Login Details',
            ],
            'REG_COACH_COMPLETE' => [
                'file' => 'registration-coach.html',
                'subject' => 'Welcome Coach – Your ACA Login Details',
            ],

            // Fees / Payment
            'INV_PAID' => [
                'file' => 'invoice-paid.html',
                'subject' => 'Payment Receipt – '.$data['invoice_no'] ?? '',
            ],
            'FEES_REMINDER' => [
                'file' => 'fees-reminder.html',
                'subject' => 'Fee Reminder – Invoice '.$data['invoice_no'] ?? '',
            ],

            // Store
            'STORE_STUDENT_ORDER' => [
                'file' => 'store-order-student.html',
                'subject' => 'Order Confirmation – '.$data['order_no'] ?? '',
            ],
            'STORE_ADMIN_ALERT' => [
                'file' => 'store-order-admin.html',
                'subject' => 'New Merchandise Order – '.$data['order_no'] ?? '',
            ],

            // Salary
            'SALARY_SLIP' => [
                'file' => 'salary-slip.html',
                'subject' => 'Salary Slip – '.$data['month_label'] ?? '',
            ],

            // Announcements
            'ANNOUNCEMENT' => [
                'file' => 'announcement.html',
                'subject' => $data['announcement_title'] ?? 'ACA Announcement',
            ],

            // Daily reports
            'DAILY_ATTENDANCE_REPORT' => [
                'file' => 'daily-attendance-report.html',
                'subject' => 'Daily Attendance Report – '.$data['report_date'] ?? '',
            ],

            // Monthly reports
            'MONTHLY_FINANCIAL_REPORT' => [
                'file' => 'monthly-financial-report.html',
                'subject' => 'Monthly Financial Report – '.$data['month_label'] ?? '',
            ],

            // System logs report
            'MONTHLY_SYSTEM_LOGS' => [
                'file' => 'system-logs-report.html',
                'subject' => 'System Logs Summary – '.$data['month_label'] ?? '',
            ],
        ];

        if (!isset($map[$templateCode])) {
            return ['subject' => "Unknown Template", 'html' => "Template code '$templateCode' not mapped."];
        }

        $file = $map[$templateCode]['file'];
        $subject = $map[$templateCode]['subject'];

        $template = $this->loadTemplate($file);
        $html = $this->applyData($template, $data);

        return [
            'subject' => $subject,
            'html' => $html
        ];
    }
}

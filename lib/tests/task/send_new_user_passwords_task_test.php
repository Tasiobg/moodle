<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

declare(strict_types=1);

namespace core\task;

use advanced_testcase;
use tool_uploaduser\cli_helper;
use tool_uploaduser\local\text_progress_tracker;

/**
 * File containing tests for send_new_user_passwords_task
 *
 * @package     core
 * @category    test
 * @covers      \core\task\send_new_user_passwords_task
 * @copyright   2025 Moodle Pty Ltd <support@moodle.com>
 * @author      2025 Tasio Bertomeu Gomez <tasio.bertomeu@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class send_new_user_passwords_task_test extends advanced_testcase {
    /**
     * Load required libraries (upload user progress tracker)
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once("{$CFG->dirroot}/{$CFG->admin}/tool/uploaduser/locallib.php");
        parent::setUpBeforeClass();
    }

    /**
     * Generate cli_helper and mock $_SERVER['argv']
     *
     * @param string $filecontent
     * @param array $mockargv
     * @return string - CSV import output
     */
    protected function process_csv_upload(string $filecontent, array $mockargv = []): string {
        $filepath = make_request_directory(false) . '/' . rand();
        file_put_contents($filepath, $filecontent);
        $mockargv[] = "--file=$filepath";

        if (array_key_exists('argv', $_SERVER)) {
            $oldservervars = $_SERVER['argv'];
        }
        $_SERVER['argv'] = array_merge([''], $mockargv);
        $clihelper = new cli_helper(text_progress_tracker::class);
        if (isset($oldservervars)) {
            $_SERVER['argv'] = $oldservervars;
        } else {
            unset($_SERVER['argv']);
        }

        ob_start();
        $clihelper->process();
        $output = ob_get_contents();
        ob_end_clean();

        return $output;
    }

    /**
     * Validate the content of the email sent to new users
     * when they are created via the upload user tool.
     */
    public function test_send_new_user_password_task(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $csv = <<<EOF
username,firstname,lastname,email
student1,Student,One,s1@example.com
EOF;
        $this->process_csv_upload($csv, ['--uutype=' . UU_USER_ADDNEW]);

        $sink = $this->redirectEmails();
        $task = new \core\task\send_new_user_passwords_task();
        ob_start();
        $task->execute();
        ob_end_clean();
        $emails = $sink->get_messages();

        // Email for the new user.
        $emailonebody = quoted_printable_decode($emails[0]->body);
        $this->assertStringContainsString("An account has been created for you at 'PHPUnit test site'", $emailonebody);
        $this->assertStringContainsString('https://www.example.com/moodle/login/?lang=en', $emailonebody);
        $this->assertStringContainsString('https://www.example.com/moodle/user/contactsitesupport.php', $emailonebody);
        $this->assertStringContainsString('PHPUnit test site: New user account', $emails[0]->subject);
    }
}

<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Ensure the repeatable teacher/student accounts used by the three-phase demo.
 *
 * Run inside the Moodle container:
 * php local/testcase_exchange/cli/ensure_demo_users.php
 *
 * @package    local_testcase_exchange
 * @copyright  2026 Nguyen Quoc Thinh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/enrollib.php');

const DEMO_COURSE_ID = 3;
const DEMO_PASSWORD = 'StudentPassword123!';

/**
 * Create or refresh a manual Moodle user.
 *
 * @param array $definition User fields.
 * @return stdClass Moodle user record.
 */
function local_tce_ensure_demo_user(array $definition): stdClass {
    global $CFG, $DB;

    $user = $DB->get_record('user', ['username' => $definition['username'], 'mnethostid' => $CFG->mnet_localhost_id]);
    if (!$user) {
        $record = (object) [
            'auth' => 'manual',
            'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
            'username' => $definition['username'],
            'password' => $definition['password'],
            'firstname' => $definition['firstname'],
            'lastname' => $definition['lastname'],
            'email' => $definition['email'],
            'lang' => 'vi',
            'timezone' => 'Asia/Bangkok',
        ];
        $userid = user_create_user($record, true, false);
        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        mtrace('Created demo user: ' . $definition['username']);
    } else {
        $changed = false;
        foreach (['firstname', 'lastname', 'email'] as $field) {
            if ((string) $user->{$field} !== (string) $definition[$field]) {
                $user->{$field} = $definition[$field];
                $changed = true;
            }
        }
        if ($changed) {
            user_update_user($user, false, false);
            $user = $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
        }
        update_internal_user_password($user, $definition['password']);
        mtrace('Refreshed demo user: ' . $definition['username']);
    }

    return $user;
}

/**
 * Ensure a user has the requested role through the manual enrolment instance.
 *
 * @param stdClass $user User record.
 * @param string $roleshortname Moodle role shortname.
 * @return void
 */
function local_tce_ensure_demo_enrolment(stdClass $user, string $roleshortname): void {
    global $DB;

    $roleid = $DB->get_field('role', 'id', ['shortname' => $roleshortname], MUST_EXIST);
    $instances = enrol_get_instances(DEMO_COURSE_ID, true);
    $manualinstance = null;
    foreach ($instances as $instance) {
        if ($instance->enrol === 'manual') {
            $manualinstance = $instance;
            break;
        }
    }
    if (!$manualinstance) {
        throw new moodle_exception('Manual enrolment is not enabled for demo course #' . DEMO_COURSE_ID);
    }

    $plugin = enrol_get_plugin('manual');
    $plugin->enrol_user($manualinstance, (int) $user->id, (int) $roleid, 0, 0, ENROL_USER_ACTIVE);
    mtrace('Ensured ' . $roleshortname . ' enrolment for ' . $user->username);
}

$teacher = local_tce_ensure_demo_user([
    'username' => 'teacher1',
    'password' => 'TeacherPassword123!',
    'firstname' => 'Giảng viên',
    'lastname' => 'Demo',
    'email' => 'teacher1@example.com',
]);
local_tce_ensure_demo_enrolment($teacher, 'editingteacher');

$student1 = local_tce_ensure_demo_user([
    'username' => 'student1',
    'password' => DEMO_PASSWORD,
    'firstname' => 'Sinh viên',
    'lastname' => 'Một',
    'email' => 'student1@example.com',
]);
local_tce_ensure_demo_enrolment($student1, 'student');

$student2 = local_tce_ensure_demo_user([
    'username' => 'student2',
    'password' => DEMO_PASSWORD,
    'firstname' => 'Sinh viên',
    'lastname' => 'Hai',
    'email' => 'student2@example.com',
]);
local_tce_ensure_demo_enrolment($student2, 'student');

$student3 = local_tce_ensure_demo_user([
    'username' => 'student3',
    'password' => DEMO_PASSWORD,
    'firstname' => 'Sinh viên',
    'lastname' => 'Ba',
    'email' => 'student3@example.com',
]);
local_tce_ensure_demo_enrolment($student3, 'student');

mtrace('Demo users are ready.');

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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * English language strings.
 *
 * @package    local_testcase_exchange
 * @copyright  2026 Nguyen Quoc Thinh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'CodeRunner Testcase Exchange';
$string['testcase_exchange:view'] = 'View testcase exchange and leaderboard';
$string['testcase_exchange:run'] = 'Run private testcase explorations';
$string['testcase_exchange:contribute'] = 'Contribute testcases';
$string['testcase_exchange:review'] = 'Review testcase contributions';
$string['testcase_exchange:manage'] = 'Manage the testcase repository';
$string['heading_dashboard'] = 'Testcase Contribution Dashboard and Leaderboard';
$string['settings_db_heading'] = 'Testcase database';
$string['settings_db_heading_desc'] = 'Connection used by the plugin to read and write testcase data.';
$string['settings_db_host'] = 'MariaDB Host';
$string['settings_db_host_desc'] = 'Database hostname, without a protocol or port.';
$string['settings_db_port'] = 'Database port';
$string['settings_db_port_desc'] = 'MariaDB/MySQL TCP port.';
$string['settings_db_user'] = 'DB User';
$string['settings_db_user_desc'] = 'Use a dedicated account. Schema migration additionally requires CREATE, ALTER and INDEX; normal operation requires SELECT, INSERT, UPDATE and DELETE.';
$string['settings_db_pass'] = 'DB Password';
$string['settings_db_pass_desc'] = 'Password for the testcase database account.';
$string['settings_db_name'] = 'DB Name';
$string['settings_db_name_desc'] = 'Name of the separate testcase database.';
$string['settings_jobe_heading'] = 'Jobe runner';
$string['settings_jobe_heading_desc'] = 'Endpoints used by this plugin for health monitoring. Actual execution uses the CodeRunner Jobe configuration, so both settings must refer to the same cluster.';
$string['settings_jobe_servers'] = 'Jobe server URLs';
$string['settings_jobe_servers_desc'] = 'Enter one server per line or separate servers with semicolons. Leave blank to monitor CodeRunner\'s configured jobe_host. The REST path is added automatically.';
$string['settings_jobe_api_key'] = 'Jobe API key';
$string['settings_jobe_api_key_desc'] = 'Optional API key used by the health check. Configure the equivalent credential in CodeRunner for actual execution.';
$string['nav_testcase_bank'] = 'Testcase Bank & Exchange';
$string['my_testcases'] = 'My Testcases';
$string['received_testcases'] = 'Received Testcases';
$string['privateexploration'] = 'Private testcase exploration';
$string['nocoderunnerquestions'] = 'This course has no CodeRunner questions in a Quiz.';
$string['selectquestion'] = 'Quiz and CodeRunner question';
$string['studentsource'] = 'Your current program source';
$string['testinput'] = 'Test input / test expression';
$string['predictedoutput'] = 'Expected output';
$string['studentoutput'] = 'Student program output';
$string['oracleoutput'] = 'Reference output';
$string['purpose'] = 'What behaviour are you testing?';
$string['reflection'] = 'What did you learn?';
$string['category'] = 'Test category';
$string['category_normal'] = 'Normal case';
$string['category_boundary'] = 'Boundary value';
$string['category_empty'] = 'Empty or missing data';
$string['category_invalid'] = 'Invalid data';
$string['category_large'] = 'Large input / performance';
$string['category_branch'] = 'Logic branch';
$string['category_other'] = 'Other';
$string['runprivate'] = 'Run privately';
$string['myruns'] = 'My private runs';
$string['mycontributions'] = 'My contributions';
$string['myrewards'] = 'Unlocked testcases';
$string['proposesharing'] = 'Propose sharing';
$string['managepolicies'] = 'Configure testcase policy';
$string['configuretestcasecontributions'] = 'Configure testcase contributions';
$string['testcaseexchangeheading'] = 'Testcase contributions';
$string['enabletestcasecontributions'] = 'Allow testcase contributions';
$string['enabletestcasecontributions_help'] = 'Students can run private testcase candidates and submit successful ones for teacher review. Advanced privacy, review and input settings are available from the Quiz testcase configuration link.';
$string['contributetestcaseafterquiz'] = 'Contribute a testcase';
$string['contributetestcaseafterquizintro'] = 'Your latest submitted code will be loaded so you can edit it and explore multiple testcases.';
$string['codeprefilledfromattempt'] = 'Your latest submitted code has been loaded. You can edit it and run multiple private testcase experiments.';
$string['backtocurrentquiz'] = 'Back to current Quiz';
$string['submitcodebeforetesting'] = 'Submit code with Check in a Quiz first, then use its Contribute a testcase button.';
$string['contributingtestcasefor'] = 'You are contributing a testcase for:';
$string['currentquiz'] = 'Quiz';
$string['currentquestion'] = 'Question';
$string['reviewcontributions'] = 'Review contributions';
$string['backtodashboard'] = 'Back to testcase dashboard';
$string['runcreatedmatch'] = 'The private run was saved. Your prediction matches the reference output.';
$string['runcreatedmismatch'] = 'The private run was saved. Review the difference before proposing it for sharing.';
$string['runcreatedoraclefailed'] = 'The private run was saved. The testcase caused an execution error with the reference oracle: {$a}. This testcase cannot be contributed.';
$string['runcreatedstudenterror'] = 'The private run was saved. Your code encountered an error with this testcase while the reference oracle succeeded.';
$string['runcreatedsaved'] = 'The private run was saved and the reference output was recorded.';
$string['contributionstatus'] = 'Contribution status: {$a}';
$string['databaseunavailable'] = 'The testcase database is unavailable. Please contact the administrator.';
$string['unexpectederror'] = 'The operation could not be completed. Please try again or contact the administrator.';
$string['featuredisabled'] = 'Testcase exploration is disabled for this Quiz.';
$string['inputtoolarge'] = 'The test input exceeds the configured size limit.';
$string['ratelimited'] = 'Too many runs. Please wait one minute and try again.';
$string['invalidjsoninput'] = 'The input is not valid JSON for this question policy.';
$string['invalidcontext'] = 'The selected question does not belong to this course and Quiz.';
$string['notcoderunner'] = 'The selected question is not a CodeRunner question.';
$string['missingoracle'] = 'The selected question has no teacher reference answer.';
$string['invalidtestcodetemplate'] = 'Template mode requires a test-code template containing {{INPUT}}.';
$string['oraclenotsuccessful'] = 'This run cannot be shared because its reference run did not succeed.';
$string['invalidrun'] = 'The private run does not exist or does not belong to you.';
$string['invalidstatus'] = 'Invalid contribution status.';
$string['invalidtransition'] = 'This contribution status transition is not allowed.';
$string['invalidcontribution'] = 'The contribution does not exist in this course.';
$string['reviewnote'] = 'Review note';
$string['needsexplanation'] = 'Needs explanation';
$string['approvecontribution'] = 'Approve';
$string['rejectcontribution'] = 'Reject';
$string['reviewsaved'] = 'The review decision was saved.';
$string['nocontributionspending'] = 'There are no contributions waiting for review.';
$string['policysaved'] = 'The Quiz and question policy was saved.';
$string['enablefeature'] = 'Enable testcase exploration and contribution';
$string['showoracle'] = 'Show reference output to students';
$string['enableleaderboard'] = 'Enable leaderboard';
$string['explanationrequired'] = 'Please explain how this testcase adds distinct value.';
$string['explanationsaved'] = 'Your explanation was saved and the contribution was resubmitted.';
$string['resubmitexplanation'] = 'Resubmit explanation';
$string['quizpolicy'] = 'Quiz policy';
$string['questionpolicy'] = 'Question policy';
$string['reviewmode'] = 'Review mode';
$string['reviewmode_teacher'] = 'Teacher review';
$string['reviewmode_auto'] = 'Automatic approval';
$string['rewardpolicy'] = 'Reward policy';
$string['rewardpolicy_oneforone'] = 'One approved contribution unlocks one testcase';
$string['runsperminute'] = 'Runs per minute';
$string['maxinputbytes'] = 'Maximum input size in bytes';
$string['inputmode'] = 'Input mode';
$string['testcodetemplate'] = 'Test-code template containing {{INPUT}}';
$string['normalization'] = 'Input normalization';
$string['categories'] = 'Allowed categories';
$string['userid'] = 'User ID';
$string['quizquestion'] = 'Quiz / question';
$string['review'] = 'Review';
$string['healthcheck'] = 'Testcase service health';
$string['databasehealth'] = 'External database';
$string['jobehealth'] = 'Jobe nodes';
$string['schemaversion'] = 'Connected; schema version {$a}.';
$string['server'] = 'Server';
$string['languages'] = 'Available languages';
$string['available'] = 'Available';
$string['unavailable'] = 'Unavailable';
$string['privacy:path'] = 'Testcase exchange';
$string['privacy:metadata:testcase_store'] = 'The external testcase repository stores private runs, contributions, reviews and rewards.';
$string['privacy:metadata:testcase_store:user_id'] = 'The Moodle user ID identifies the owner, reviewer or reward recipient.';
$string['privacy:metadata:testcase_store:input'] = 'The testcase input submitted by the user.';
$string['privacy:metadata:testcase_store:source_hash'] = 'A one-way hash of the submitted program source.';
$string['privacy:metadata:testcase_store:outputs'] = 'Predicted, student-program and reference outputs from a testcase run.';
$string['privacy:metadata:testcase_store:reflection'] = 'The purpose, category and reflection supplied with a testcase.';
$string['privacy:metadata:jobe'] = 'Program source and testcase input are sent to the configured Jobe sandbox for execution and are not intentionally persisted there by this plugin.';
$string['privacy:metadata:jobe:source'] = 'Student program source or the CodeRunner reference solution.';
$string['privacy:metadata:jobe:input'] = 'The testcase input used for sandbox execution.';
$string['coursecontributions'] = 'Class contributed testcases';
$string['allcontributions'] = 'All contributions';
$string['student'] = 'Student';
$string['quiz'] = 'Quiz';
$string['question'] = 'Question';
$string['filterbyquestion'] = 'Filter by question';
$string['filterbystatus'] = 'Filter by status';
$string['allquestions'] = 'All questions in course';
$string['allstatuses'] = 'All statuses';
$string['pending'] = 'Pending';
$string['status_submitted'] = 'Submitted';
$string['status_approved'] = 'Approved';
$string['status_rejected'] = 'Rejected';
$string['status_duplicate'] = 'Duplicate';
$string['status_needs_explanation'] = 'Needs explanation';
$string['status_archived'] = 'Archived';
$string['nocontributionsfound'] = 'No student contributed testcases found matching this filter.';
$string['actions'] = 'Actions';
$string['starrating'] = 'Star rating';
$string['stars'] = 'stars';
$string['norating'] = 'No rating';
$string['teachercomment'] = 'Teacher comment';
$string['saverating'] = 'Save rating';
$string['rating_5_desc'] = 'Excellent / Edge case';
$string['rating_4_desc'] = 'Very good';
$string['rating_3_desc'] = 'Good';
$string['rating_2_desc'] = 'Normal';
$string['rating_1_desc'] = 'Basic';
$string['coursehub'] = 'Testcase Bank — Course Hub';
$string['coursehub_desc'] = 'Select a course below to view the student testcase bank, review pending contributions, or configure policies.';
$string['coursehub_review_title'] = 'Review Contributions — Course Hub';
$string['coursehub_review_desc'] = 'Select a course to review student contributed testcases.';
$string['courseswithcoderunner'] = 'Courses with CodeRunner questions';
$string['switchcourse'] = 'Switch course';
$string['allcourses'] = 'All courses';
$string['viewtestcasebank'] = 'Open testcase bank';
$string['reviewcontributionsbtn'] = 'Review contributions';
$string['totalcontributions'] = 'Total contributions';
$string['pendingcount'] = 'Pending';
$string['approvedcount'] = 'Approved';
$string['nocourseswithcoderunner'] = 'No courses with CodeRunner questions were found on this site.';
$string['backtocoursehub'] = 'Back to course list';


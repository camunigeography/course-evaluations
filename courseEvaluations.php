<?php


# Class to provide a course evaluations system
require_once ('frontControllerApplication.php');
class courseEvaluations extends frontControllerApplication
{
	# Specify available arguments as defaults or as NULL (to represent a required argument)
	public function defaults ()
	{
		return $defaults = array (
			'hostname' => 'localhost',
			'username' => 'assessments',
			'database' => 'assessments',
			'globalPeopleDatabase' => 'people',
			'div' => 'courseevaluations',
			'table' => 'people',
			'applicationName' => 'Course evaluations',
			'administrators' => true,
			'tabUlClass' => 'tabsflat',
			'authentication' => true,
			'feedback' => false,
			'yearStartMonth' => 12,
			'includePieCharts' => true,
			'piechartStub' => '/images/piechart',
			'pieChartWidth' => 250,
			'pieChartHeight' => 200,
			'phpmyadminUrl' => '/phpmyadmin/',
			'denyResults' => array (),
			'overrideYear' => false,
			'overrideUserYeargroup' => false,
			'additionalLecturersResultsAccess' => array (),
			'userNameCallback' => 'userNameCallback',			// Callback function
			'overrideQuestionLabels' => array (),		// Array of academicYear => array (table => array (questionId => questionTitle))
			'userSwitcherUsers' => array ($this, 'userSwitcherUsers'),
			'userSwitcherOnSwitch' => array ($this, 'userSwitcherOnSwitch'),
		);
	}
	
	
	# Specify additional actions
	public function actions ()
	{
		# Define the actions
		return $actions = array (
			'data' => array (
				'description' => 'Submit an evaluation',
				'tab' => 'Evaluations',
				'url' => '',
			),
			'results' => array (
				'description' => 'Results',
				'tab' => 'Results',
				'url' => 'results/',
				'enableIf' => $this->userHasResultsAccess,
			),
			'rates' => array (
				'description' => 'Submission rates',
				'tab' => 'Submission rates',
				'url' => 'rates.html',
				'restrictedAdministrator' => true,
				'parent' => 'admin',
			),
			'export' => array (
				'description' => 'Export data',
				'url' => 'export.html',
				'restrictedAdministrator' => true,
				'parent' => 'admin',
				'subtab' => 'Export data',
				'export' => true,
			),
			'import' => array (
				'description' => 'Set up course/student details',
				'url' => 'import.html',
				'restrictedAdministrator' => true,
				'parent' => 'admin',
				'subtab' => 'Set up course/student details',
			),
			
			# Override settings default to enable restricted administrators to access
			'settings' => array (
				'description' => 'Settings',
				'url' => 'settings.html',
				'restrictedAdministrator' => true,	// Normally 'administrator' => true
				'parent' => 'admin',
				'subtab' => 'Settings',
			),
		);
	}
	
	
	# Database structure definition
	public function databaseStructure ()
	{
		return "
			CREATE TABLE `administrators` (
			  `username__JOIN__people__people__reserved` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Username',
			  `active` enum('','Yes','No') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Yes' COMMENT 'Currently active?',
			  `privilege` enum('Administrator','Restricted administrator') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Administrator' COMMENT 'Administrator level',
			  PRIMARY KEY (`username__JOIN__people__people__reserved`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='System administrators';
			
			CREATE TABLE `courses` (
			  `id` int NOT NULL AUTO_INCREMENT COMMENT 'Unique key',
			  `year` varchar(9) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Academic year',
			  `yeargroup` enum('ia','ib','ii') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Year-group',
			  `type` enum('','courses','fieldtrips','practicals','projects','dissertation','general') COLLATE utf8mb4_unicode_ci DEFAULT 'courses' COMMENT 'Type of module',
			  `url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'URL fragment',
			  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Course title',
			  `entries` enum('0','1') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0' COMMENT 'Whether the course requires entries (=0) or is instead available for assessment by all students in the year (=1)',
			  `paper` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Paper number',
			  PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Courses';
			
			CREATE TABLE `entries` (
			  `id` int NOT NULL AUTO_INCREMENT COMMENT 'Unique key',
			  `crsid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Username',
			  `year` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Year',
			  `yeargroup` enum('IA','IB','II') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Year-group',
			  `paper` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Paper number',
			  PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Examination entries';
			
			CREATE TABLE `feedbackcourses` (
			  `id` int NOT NULL AUTO_INCREMENT,
			  `user` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Username',
			  `courseId` int NOT NULL COMMENT 'Course',
			  `q1howmany` enum('','All','Most','About half','Less than half') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '1. How many lectures in this %type have you attended?',
			  `q2overall` enum('','Excellent','Good','Fair','Poor','No opinion') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '2. In general, how did you find this %type?',
			  `q3stimulating` enum('','Agree strongly','Agree','Disagree','Disagree strongly','No opinion') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '3. To what extent do you agree that the %type was intellectually stimulating?',
			  `qsubcoursemodeextra4connection` enum('','Agree strongly','Agree','Disagree','Disagree strongly','No opinion') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '4. To what extent do you agree that the sections of the paper connect logically with one another?',
			  `q4enjoy` mediumtext COLLATE utf8mb4_unicode_ci COMMENT '4. Which aspect of the %type did you particularly enjoy?',
			  `q5improvement` mediumtext COLLATE utf8mb4_unicode_ci COMMENT '5. Do you have any suggestions on how the %type might be improved?',
			  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date/time submitted',
			  PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Feedback from fieldtrips/practicals/projects';
			
			CREATE TABLE `feedbackgeneral` (
			  `id` int NOT NULL AUTO_INCREMENT,
			  `user` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Username',
			  `courseId` int NOT NULL COMMENT 'Course ID',
			  `q1library` mediumtext COLLATE utf8mb4_unicode_ci COMMENT '1. The Library resources and services are good enough for my needs – please comment',
			  `q2it` mediumtext COLLATE utf8mb4_unicode_ci COMMENT '2. I have been able to access general IT resources when I have needed to – please comment',
			  `q3facilities` mediumtext COLLATE utf8mb4_unicode_ci COMMENT '3. I have been able to access specialised equipment, facilities or rooms when I have needed to – please comment',
			  `q4confidence` mediumtext COLLATE utf8mb4_unicode_ci COMMENT '4. The course has helped me to present myself with confidence – please comment',
			  `q5communication` mediumtext COLLATE utf8mb4_unicode_ci COMMENT '5. My communication skills have improved – please comment',
			  `q6problemsolving` mediumtext COLLATE utf8mb4_unicode_ci COMMENT '6. As a result of my studies, I feel confident in tackling unfamiliar problems – please comment',
			  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date/time submitted',
			  PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Feedback on general matters';
			
			CREATE TABLE `feedbacklecturers` (
			  `id` int NOT NULL AUTO_INCREMENT,
			  `user` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
			  `lecturerId` int NOT NULL COMMENT 'Lecturer',
			  `q1howmany` enum('','All','Most','About half','Less than half') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '1. How many lectures by this lecturer have you attended?',
			  `q2overall` enum('','Excellent','Good','Fair','Poor','No opinion') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '2. In general, how did you find this set of lectures?',
			  `q3stimulating` enum('','Agree strongly','Agree','Disagree','Disagree strongly','No opinion') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '3. To what extent do you agree that the lectures were intellectually stimulating?',
			  `q4presentation` enum('','Agree strongly','Agree','Disagree','Disagree strongly','No opinion') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '4. To what extent do you agree that the lectures were clearly presented?',
			  `q5readinglists` enum('','Agree strongly','Agree','Disagree','Disagree strongly','No opinion') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '5. To what extent do you agree that reading lists were satisfactory?',
			  `q6enjoy` mediumtext COLLATE utf8mb4_unicode_ci COMMENT 'Which aspect of this set of lectures did you particularly enjoy?',
			  `q7improvement` mediumtext COLLATE utf8mb4_unicode_ci COMMENT 'Do you have any suggestions on how this set of lectures might be improved?',
			  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date/time submitted',
			  PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Feedback from lecturers';
			
			CREATE TABLE `feedbackdissertation` (
			  `id` int NOT NULL AUTO_INCREMENT,
			  `user` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Username',
			  `courseId` int NOT NULL COMMENT 'Course ID',
			  `q1overall` enum('','Agree strongly','Agree','Disagree','Disagree strongly','No opinion') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '1. This set of lectures has given me more confidence in developing a dissertation idea than at the start of the year',
			  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date/time submitted',
			  PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Dissertation feedback';
			
			CREATE TABLE `feedbackothers` (
			  `id` int NOT NULL AUTO_INCREMENT,
			  `user` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Username',
			  `courseId` int NOT NULL AUTO_INCREMENT COMMENT 'Course ID',
			  `q1overall` enum('','Excellent','Good','Fair','Poor','No opinion') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '1. In general, how did you find this %type?',
			  `q2astimulating` enum('','Agree strongly','Agree','Disagree','Disagree strongly','No opinion') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '2a. The %type was intellectually stimulating.',
			  `q2bknowledge` enum('','Agree strongly','Agree','Disagree','Disagree strongly','No opinion') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '2b. The %type provided me with new knowledge.',
			  `q2cintegration` enum('','Agree strongly','Agree','Disagree','Disagree strongly','No opinion') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '2c. Sessions/days linked together well.',
			  `q3enjoy` mediumtext COLLATE utf8mb4_unicode_ci COMMENT '3. Which aspect of the %type did you particularly enjoy?',
			  `q4improvement` mediumtext COLLATE utf8mb4_unicode_ci COMMENT '4. Do you have any suggestions about how the %type might be improved?',
			  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date/time submitted',
			  PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Feedback from fieldtrips/practicals/projects';
			
			CREATE TABLE `lecturers` (
			  `id` int NOT NULL AUTO_INCREMENT COMMENT 'Unique key',
			  `year` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Year',
			  `yeargroup` enum('IA','IB','II') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Year-group',
			  `course` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Course',
			  `subcourseId` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Sub-course URL moniker, if relevant',
			  `subcourseName` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Sub-course name, if relevant',
			  `lecturer` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Lecturer',
			  PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lecturing assessments';
			
			CREATE TABLE `settings` (
			  `id` int NOT NULL AUTO_INCREMENT COMMENT 'Automatic key',
			  `opening` datetime NOT NULL COMMENT 'Opening date',
			  `closing` datetime NOT NULL COMMENT 'Closing date',
			  `allowViewingDuringSubmitting` TINYINT NULL DEFAULT NULL COMMENT 'Allow staff to view results while submission ongoing?',
			  `introductionHtml` mediumtext COLLATE utf8mb4_unicode_ci COMMENT 'Introduction text',
			  PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Settings';
		";
	}
	
	
	# Define the table names, which may be overriden by year later
	private $tables = array (
		'courses'		=> 'feedbackcourses',
		'lecturers'		=> 'feedbacklecturers',
		'others'		=> 'feedbackothers',		// E.g. for practicals
		'dissertation'	=> 'feedbackdissertation',
		'general'		=> 'feedbackgeneral',
	);
	
	
	# Define the non-course types
	#!# May be able to get rid of this now that all entries are defined explicitly, though check this doesn't disrupt the "how did you find this [name]?" label
	var $types = array (
		'courses' => array (
			'name' => 'Courses',
			'singular' => 'course',
			'ia' => 0,
			'ib' => 0,
			'ii' => 0,	/* Means unlimited, i.e. as many as are in the database */
		),
		'practicals' => array (
			'name' => 'Practicals',
			'singular' => 'practical',
			#!# Need to get rid of this numbering system
			'ia' => 5,
		),
		'projects' => array (
			'name' => 'Projects',
			'singular' => 'project',
			'ib' => 3,
		),
		'dissertation' => array (
			'name' => 'Dissertation',
			'singular' => 'dissertation',
			'ib' => 1,
			'ii' => 1,
		),
		'fieldtrips' => array (
			'name' => 'Fieldtrips',
			'singular' => 'fieldtrip',
			'ib' => 1,
		),
		'general' => array (
			'name' => 'Learning resources and Personal development',
			'singular' => 'aspect',
			'ia' => 1,
			'ib' => 1,
			'ii' => 1,
		),
	);
	
	# Fields to exclude in subcourse mode
	private $subcourseModeExcludeFields = array ('q1howmany', 'q2overall', 'q3stimulating', 'q4presentation', 'q5readinglists', );
	
	
	# Pre-actions logic
	public function mainPreActions ()
	{
		# Load additional required libraries
		require_once ('timedate.php');
		
		# Determine the current academic year, e.g. '2020-2021'
		$this->currentAcademicYear = timedate::academicYear ($this->settings['yearStartMonth'], $asRangeString = true);
		
		# Override the current year if fixed
		if ($this->settings['overrideYear']) {$this->currentAcademicYear = $this->settings['overrideYear'];}
		
		# Get the user details
		$this->userDetails = $this->getUserDetails ();
		
		# Students can never see results
		$this->userHasResultsAccess = ($this->userDetails && ($this->userDetails['type'] != 'student'));
	}
	
	
	# Constructor
	public function main ()
	{
		# Do not load these on the generic feedback page
		if ($this->action == 'feedback') {return;}
		
		# Perform data integrity checks or end
		if (!$this->userDataIntegrity ()) {return false;}
		
   		# Confirm the user exists
		if (!$this->userDetails) {
			echo "\n<p>Welcome. You do not appear to be registered on this system. If you think you should be, please <a href=\"{$this->baseUrl}/feedback.html\">contact the Webmaster</a>.</p>";
			return false;
		}
		
		# Override the current year if fixed
		if ($this->settings['overrideUserYeargroup']) {$this->userDetails['yeargroup'] = $this->settings['overrideUserYeargroup'];}
		
		# Make a hash of the user's year; note that this is taken from the database-retrieved value to prevent any encoding mismatches
		$this->userMd5 = md5 ($this->userDetails['crsid']);
		
		# Determine which courses the user can access
		$this->assessing = $this->getAssessing ($this->user);
		
		# Get current data for this user
		$this->submissions = $this->getSubmissionsOfCurrentUser ();
		
		# Make a placeholder for the type and course ID of the current data page (if any)
		$this->type = NULL;
		$this->courseId = NULL;
		
		# If the user is an administrator, run the duplicates check
		if ($this->userIsAdministrator) {
			$this->runDuplicatesCheck ();
		}
	}
	
	
	# Function to run a duplicates check; this can happen when a form is submitted quickly, and so the switching between INSERT/UPDATE is too slow, i.e. Time-of-check-to-time-of-use condition
	#!# Need to understand and eliminate the underlying issue, if it still exists
	private function runDuplicatesCheck ()
	{
		$duplicatesProblemsHtml = '';
		$checkTables = array ($this->tables['courses'], $this->tables['lecturers'], $this->tables['others']);
		foreach ($checkTables as $checkTable) {
			#!# Hard-coded year, not taking account of $this->settings['overrideYear']
			$query = "SELECT
					id,user,courseId, COUNT(courseId) AS 'Submissions for this {Username+Course}'
				FROM {$this->settings['database']}.{$checkTable}
				WHERE YEAR(`timestamp`) = " . date ('Y') . "
				GROUP BY user,courseId
				HAVING COUNT(courseId) > 1
			;";
			if ($results = $this->databaseConnection->getData ($query, "{$this->settings['database']}.{$checkTable}")) {
				$phpmyadminUrl = "{$this->settings['phpmyadminUrl']}/tbl_select.php?db={$this->settings['database']}&table={$checkTable}";
				$duplicatesProblemsHtml .= "\n<div class=\"box\">";
				$duplicatesProblemsHtml .= "\n\t<p class=\"warning\">The following data in <strong><a href=\"" . htmlspecialchars ($phpmyadminUrl) . "\" target=\"_blank\">{$this->settings['database']}.{$checkTable}</a></strong> is duplicated (probably caused by multiple quick submissions), as found by this query:</p>";
				$duplicatesProblemsHtml .= "\n\t<p class=\"warning\"><tt>{$query}</tt></p>";
				$headings = $this->databaseConnection->getHeadings ($this->settings['database'], $checkTable);
				$headings['id'] = 'id (ignore this)';
				$duplicatesProblemsHtml .= application::htmlTable ($results, $headings, 'lines compressed', false);
				$duplicatesProblemsHtml .= "\n</div>";
			}
		}
		if ($duplicatesProblemsHtml) {
			echo $duplicatesProblemsHtml;
			application::utf8Mail ($this->settings['webmaster'], 'Duplicate data issue in course assessments system', strip_tags ($duplicatesProblemsHtml), "From: {$this->settings['webmaster']}");
		}
	}
	
	
	# Home page
	public function home ()
	{
		# Welcome message
		$html  = "\n<p>Welcome. You are logged in as <strong>{$this->userDetails['name']}</strong>.</p>";
		
		# Deal with the submission half
		$html .= $this->submissionSystem ();
		
		# Show the HTML
		echo $html;
	}
	
	
	# Results
	public function results ()
	{
		# Start the HTML
		$html = '';
		
		# Get the list of available years in the data
		$years = $this->getAvailableYears ();
		
		# If there is no selected year, redirect to a URL containing the current academic year, to ensure that results for the current year are always consistently at the same URL, not left as /results/
		if (!isSet ($_GET['academicyear'])) {
			$url = $_SERVER['_SITE_URL'] . $this->baseUrl . '/results/' . $this->currentAcademicYear . '/';
			$html = application::sendHeader (302, $url);
			echo $html;
			return;
		}
		
		# Ensure the selected year is valid
		if (!in_array ($_GET['academicyear'], $years)) {
			$this->page404 ();
			return false;
		}
		
		# Set the selected year
		$this->currentAcademicYear = $_GET['academicyear'];
		
		# Show a droplist of years
		$html .= $this->yearsDroplist ($years, $this->currentAcademicYear);
		
		# Show results
		$html .= $this->showResults ();
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to get the list of available years in the data
	private function getAvailableYears ($yearsBack = 5)
	{
		# Get the data
		$query = "SELECT
			DISTINCT year
			FROM courses
			WHERE (SUBSTR(year, 1, 4) >= (YEAR(CURDATE()) - {$yearsBack}))
			ORDER BY year
		;";
		$years = $this->databaseConnection->getPairs ($query);
		
		# Ensure the current academic year is present
		$years = array_merge ($years, array ($this->currentAcademicYear));
		
		# Return the list
		return ($years);
	}
	
	
	# Function to create a droplist of years
	private function yearsDroplist ($years, $defaultYear)
	{
		# Create the list of URLs
		$urls = array ();
		foreach ($years as $year) {
			$url = $this->baseUrl . '/results/' . $year . '/';
			$urls[$url] = $year;
		}
		
		# Get the selected value
		$yearUrls = array_flip ($urls);
		$selected = $yearUrls[$defaultYear];
		
		# Create the droplist, which includes the redirection processor
		$html = application::htmlJumplist ($urls, $selected, '', 'jumplist', 0, 'jumplist', $introductoryText = 'Show results for year:');
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to export the result data to a CSV
	public function export ()
	{
		# Show results
		echo $this->showResults ($csv = true);
	}
	
	
	# Function to ensure various aspects of data integrity relating to usernames (that they are in the people database, that students are correctly marked as such and that there is no crossover)
	private function userDataIntegrity ()
	{
		# Ensure the lecturers and students are all in the people database
		#!# Get rid of the anomaly between lecturer/crsid
		$check = array ('lecturers' => 'lecturer', 'entries' => 'crsid');
		foreach ($check as $table => $field) {
			$query = "SELECT DISTINCT {$field} FROM assessments.{$table} WHERE year = '{$this->currentAcademicYear}' AND {$field} NOT IN (SELECT username FROM {$this->settings['globalPeopleDatabase']}.people);";
			if ($data = $this->databaseConnection->getPairs ($query)) {
				if ($this->action != 'import') {
					// $error = "The following user(s) present in assessments.{$table}.{$field} were not found in the user database ({$this->settings['globalPeopleDatabase']}.people) :\n\n" . print_r ($data, true);
					$error = "The following user(s) present in the imported setup data are not matching any known user. Please check for any typos and update the data, or if the usernames are correct, add the users to the Contacts Database.\n\n" . print_r ($data, true);
					echo $this->reportError ($error);
					return false;
				}
			}
		}
		
		# Check that all students are marked as students (staffType: 1) and have the course as undergraduate*
		#!# Need to eradicate direct lookup of staffType here - move to isUndergraduate as used elsewhere in this class
		$query = "SELECT
			DISTINCT crsid
			FROM assessments.entries
			LEFT OUTER JOIN {$this->settings['globalPeopleDatabase']}.people ON assessments.entries.crsid = {$this->settings['globalPeopleDatabase']}.people.username
			WHERE
				year = '{$this->currentAcademicYear}'
				AND (
					course__JOIN__people__courses__reserved NOT REGEXP '^undergraduate'
					OR (
						staffType__JOIN__people__staffType__reserved != '1'
						AND staffType__JOIN__people__staffType__reserved != '2'	/* This AND clause is to deal with Undergraduates who have gone onto be graduates, though is not ideal */
					)
				)
		;";
		if ($data = $this->databaseConnection->getPairs ($query)) {
			$error = "The following user(s) present in assessments.{$table}.{$field} were not marked as undergraduates in the user database ({$this->settings['globalPeopleDatabase']}.people) :\n\n" . print_r ($data, true);
			//echo $this->reportError ($error);
			//return false;
		}
		
		/*
		#!# This is disabled as it is very slow - find a way to find the intersection of assessments.entries.crsid and assessments.lecturers.lecturer. This is probably slow because of the use of IN() rather than a join or subtable.
		# Check that no-one is both a student is also a lecturer
		$query = "
			SELECT DISTINCT crsid AS user FROM assessments.entries WHERE year = '{$this->currentAcademicYear}' AND crsid IN (SELECT lecturer FROM assessments.lecturers WHERE year = '{$this->currentAcademicYear}')
			UNION
			SELECT DISTINCT lecturer AS user FROM assessments.lecturers WHERE year = '{$this->currentAcademicYear}' AND lecturer IN (SELECT crsid FROM assessments.entries WHERE year = '{$this->currentAcademicYear}')
		";
		if ($data = $this->databaseConnection->getPairs ($query, true)) {
			$error = "The following user(s) appear as both an undergraduate in assessments.entries.crsid and as a lecturer in assessments.lecturers.lecturer :\n\n" . print_r ($data, true);
			echo $this->reportError ($error);
			return false;
		}
		*/
		
		# Return true since all tests have been passed
		return true;
	}
	
	
	# Function to get user details
	private function getUserDetails ()
	{
		# Get the user (who must be 'active') from the master database or end
		$returnFalseIfGone = true;
		if (in_array ($this->user, $this->settings['additionalLecturersResultsAccess'])) {
			$returnFalseIfGone = false;
		}
		
		# Get the user via the supplied callback
		$userNameCallback = $this->settings['userNameCallback'];
		$person = $userNameCallback ($this->user, $returnFalseIfGone);
		
		# Determine the user type or end
		if (!$userType = $this->userType ($person)) {
			return false;
		}
		
		# Get the user's yeargroup (e.g. 'ia') if they are a student
		#!# This needs to take account of overrideYear which effectively rewinds time and therefore their yeargroup will be different
		$yearGroupOfUser = $this->yearGroupOfUser ($person, $userType);
		if ($yearGroupOfUser === false) {
			#!# Report error
			return false;
		}
		
		# Assignments
		#!# Refactor these out by changing them lower down the code hierarchy
		$user = array (
			'crsid'					=> $person['username'],
			'name'					=> $person['name'],
			'type'					=> $userType,
			'yeargroup'				=> $yearGroupOfUser,
			'resultsOtherCourses'	=> ($userType == 'administrator' || $userType == 'organiser'),	// Full/restricted administrators can view other courses
			'resultsOtherLecturers'	=> ($userType == 'administrator'), // Full administrators only
		);
		
		# Return the user
		return $user;
	}
	
	
	# Function to determine a year group (applies to students only), e.g. 'ia'
	private function yearGroupOfUser ($person, $userType)
	{
		# If not a student, return NULL signifying an empty (but correct) result
		if ($userType != 'student') {return NULL;}
		
		# Extract the year from the course, e.g. 'undergraduate2020' or 'undergraduate2020education' are '2020'
		if (!preg_match ('([0-9]{4})', $person['course__JOIN__people__courses__reserved'], $matches)) {return false;}
		$userStartYear = $matches[0];
		
		# Get the groups
		$groups = $this->undergraduateYearGroupsForCurrentAcademicYear ();
		
		# End if not found
		if (!isSet ($groups[$userStartYear])) {return false;}
		
		# Lookup the year group
		$yearGroup = $groups[$userStartYear];
		
		# Return the year group, e.g. 'ia'
		return $yearGroup;
	}
	
	
	# Function to determine the user type, which starts with the most restrictive first, then the local administrator database, for security
	private function userType ($user)
	{
		# See also "SECURITY MODEL" section elsewhere in this file
		
		# Check for a student in the people database (type is '1')
		if ($user['isUndergraduate']) {
			return 'student';
		}
		
		# Restricted administrators
		if ($this->restrictedAdministrator) {
			return 'organiser';
		}
		
		# Application admins
		if ($this->userIsAdministrator) {
			return 'administrator';
		}
		
		# Staff
		#!# This is still too broad really
		if ($user['isStaffInternal']) {
			return 'lecturer';
		}
		
		# Graduates who teach
		if ($user['isGraduate']) {
			return 'lecturer';
		}
		
		# Hasn't been found
		return false;
	}
	
	
	# Function to get the year groups
	private function undergraduateYearGroupsForCurrentAcademicYear ()
	{
		# Get the current academic year, e.g. '2020'
		$currentYearStart = timedate::academicYear ($this->settings['yearStartMonth']);
		
		# Create a lookup between the year and the label
		$groups = array (
			$currentYearStart => 'ia',
			($currentYearStart - 1) => 'ib',
			($currentYearStart - 2) => 'ii',
		);
		
		# Return the groups
		return $groups;
	}
	
	
	# Function to deal with data submission/viewing
	public function data ()
	{
		# Ensure the facility is open
		#!# Inconsistent here
		if (!$this->facilityIsOpen ($html)) {
			echo $html;
			return false;
		}
		
		# Determine the type of module being assessed of viewed; this sets $this->type
		if (!$this->determineType ()) {
			$this->page404 ();
			return false;
		}
		
		# Show the relevant form
		$formFunction = $this->type . 'Form';	// e.g. coursesForm, fieldtripsForm, practicalsForm, projectsForm, generalForm
		$this->{$formFunction} ();
	}
	
	
	# Function to determine the type of module being assessed or viewed
	#!# Should these checks be run if in home() rather than data() ?
	private function determineType ()
	{
		# End if no assessing
		if (!$this->assessing) {return false;}
		
		# Ensure that the URL hasn't been fiddled; all these parameters should be set, even if they are not empty
		$parameters = array ('action', 'year', 'yeargroup', 'module', 'item');
		foreach ($parameters as $parameter) {
			if (!isSet ($_GET[$parameter])) {
				return false;
			}
		}
		
		#!# Ensure the year and yeargroup are correct, e.g. {baseUrl}/2100-2102/ia/fieldtrips/berlin/ works!
		
		# Determine the current overall type and the specific module; hence if the module type is not found, default to courses
		foreach ($this->types as $type => $attributes) {
			
			# Check for a generic module type (i.e. fieldtrip/practical/project
			if ($_GET['module'] == $type) {
				
				# Skip if this type isn't being assessed (this would be due to the user fiddling the URL)
				if (!isSet ($this->assessing[$type])) {continue;}
				
				# If found, sanity-check that there enough available projects for the number to be assessed in this yeargroup
				if (count ($this->assessing[$type]) < $attributes[$this->userDetails['yeargroup']]) {
					#!# Use generic error-throwing stuff
					$errorMessage = 'Data mismatch: There are not enough available projects for the number to be assessed in this yeargroup.';
					application::utf8Mail ($this->settings['webmaster'], 'System error in Teaching assessment', wordwrap ($errorMessage), "From: {$this->settings['webmaster']}");
					return false;
				}
				
				# Set the particular item if a particular item is set
				if ($_GET['item']) {
					foreach ($this->assessing[$type] as $index => $course) {
						if ($_GET['item'] == $course['url']) {
							$this->type = $type;
							$this->courseId = $index;
							return true;
						}
					}
					
					# If not found, then an incorrect item has been submitted
					if (!$this->courseId) {
						return false;
					}
				}
				
				# Otherwise set only the type
				$this->type = $type;
				return true;
			}
		}
		
		# No type found, so it should be a course; ensure the specified course is within the user's list of those being assessed, and assign it
		foreach ($this->assessing['courses'] as $index => $course) {
			if ($_GET['module'] == $course['url']) {
				$this->type = 'courses';
				$this->courseId = $index;
				return true;
			}
		}
		
		# Type not found
		return false;
	}
	
	
	# Courses form
	private function coursesForm ()
	{
		# Determine which course
		$course = ($this->assessing['courses'][$this->courseId]);
		
		# Get the course title
		$courseTitle = /* strtoupper ($course['yeargroup']) . ' ' . */ htmlspecialchars ($course['title']);
		
		# Get the lecturers for this course
		$courseUrl = $_GET['module'];
		$lecturers = $this->getLecturers ($courseUrl);
		
		# In some cases, the courses have subcourses, so always regroup lecturers, so that we can add headers below (which will only happen if there is a subcourse) between each group
		$lecturersBySubcourse = application::regroup ($lecturers, 'subcourseName', false);	// We actually want to sort by subcourseId but show subcourseName; however, application::regroup works through the grouping in order, so the ORDER BY subcourseId will be maintained naturally
		$subcourseMode = (count ($lecturersBySubcourse) != 1);
		
		# Create the form
		$form = new form (array (
			'displayRestrictions' => false,
			'databaseConnection' => $this->databaseConnection,
			'nullText' => '',
			'formCompleteText' => "Thanks for submitting your assessment. Please now return to the <a href=\"{$this->baseUrl}/\">main overview page</a> (though you can <a href=\"{$_SERVER['REQUEST_URI']}\">edit this submission further</a> if necessary).",
			'linebreaks' => false,
			'div' => 'ultimateform assessments courses',
			'titleReplacements' => array ('%type' => $this->types[$this->type]['singular']),
			'rows' => 6,
			'cols' => 50,
		));
		
		# Introduction
		$form->heading ('p', "This assessment form for <strong>{$courseTitle}</strong> is divided into two sections: (i) the course overall, and (ii) an assessment for each lecturer who has given 4 or more lectures.<br /><br />");
		
		# Databind the main course questions
		$data['course'] = ((isSet ($this->submissions[$this->type]) && isSet ($this->submissions[$this->type][$this->courseId])) ? $this->submissions[$this->type][$this->courseId] : array ());
		$isUpdate = (!empty ($data['course']));
		$exclude = array ('id','user','courseId', 'timestamp');
		if (!$subcourseMode) {
			$alsoExclude = array ('qsubcoursemodeextra4connection', );
			$exclude = array_merge ($exclude, $alsoExclude);
		}
		$table = $this->tables['courses'];
		$attributes = array (
			'q1howmany' => array ('heading' => array ('3' => "Overview: {$courseTitle}")),
			'qsubcoursemodeextra4connection' => array ('required' => true, ),
			'q4enjoy' => array ('title' => ($subcourseMode ? '5' : '4') . '. Which aspect of the course did you particularly enjoy?', ),
			'q5improvement' => array ('title' => ($subcourseMode ? '6' : '5') . '. Do you have any suggestions on how the course might be improved?', ),
		);
		$attributes = $this->overrideQuestionLabels ($table, $attributes);
		$form->dataBinding (array (
			'database' => $this->settings['database'],
			'table' => $table,
			'prefix' => "course{$course['id']}",
			'exclude' => $exclude,
			'data' => $data['course'],
			'attributes' => $attributes,
		));
		
		# Databind the form for each lecturer
		$form->heading (3, "Lecturers: {$courseTitle}");
		$totalLecturers = count ($lecturers);
		$form->heading ('p', ($totalLecturers == 1 ? 'There is a separate assessment for the lecturer:' : "There is a separate assessment for each of the {$totalLecturers} lecturers:"));
		
		# State that they can skip any lecturer if required
		if (count ($lecturersBySubcourse) > 1) {
			$form->heading ('', '<p class="noteskippable">Please make any comments on any specific lecture material in the space provided below. (Then click Submit at end.)</p>');
		}
		$data['lecturers'] = array ();
		foreach ($lecturersBySubcourse as $subcourse => $lecturers) {
			
			# Determine the fields to exclude
			$exclude = array ('id','user','timestamp');
			
			# In subcourse mode, add a subheading
			if ($subcourseMode) {
				$form->heading ('', '<h4>' . htmlspecialchars ($subcourse) . '</h4>');
				$exclude = array_merge ($exclude, $this->subcourseModeExcludeFields);
			}
			
			# Add this block
			$table = $this->tables['lecturers'];
			foreach ($lecturers as $key => $lecturer) {
				$data['lecturers'][$key] = ((isSet ($this->submissions['lecturers']) && isSet ($this->submissions['lecturers'][$key])) ? $this->submissions['lecturers'][$key] : array ());
				$attributes = array (
					'lecturerId' => array ('type' => 'select', 'editable' => false, 'values' => array ($lecturer['id'] => $lecturer['name']), 'default' => $lecturer['id']),
					'q1howmany' => array ('required' => true, ),
					'q2overall' => array ('required' => true, ),
					'q6enjoy' => array ('rows' => 4, 'title' => ($subcourseMode ? '1' : '6') . '. Which aspect of this set of lectures did you particularly enjoy?', ),
					'q7improvement' => array ('rows' => 4, 'title' => ($subcourseMode ? '2' : '7') . '. Do you have any suggestions on how this set of lectures might be improved?', ),
				);
				$attributes = $this->overrideQuestionLabels ($table, $attributes);
				$form->dataBinding (array (
					'database' => $this->settings['database'],
					'table' => $table,
					'data' => $data['lecturers'][$key],
					'prefix' => "lecturer{$key}",
					'exclude' => $exclude,
					'attributes' => $attributes,
				));
			}
		}
		
		# Show the results on screen
		$form->setOutputScreen ();
		
		# Send a backup copy to the administrator
		$subject = "Teaching assessment: {$this->user} - {$this->userDetails['yeargroup']} - {$this->type} - {$courseUrl}" . ($isUpdate ? ' (update)' : '');
		$form->setOutputEmail ($this->settings['webmaster'], $this->settings['webmaster'], $subject, "From: {$this->settings['webmaster']}");
		
		# Obtain the result
		if (!$result = $form->process ()) {return false;}
		
		# Insert the course data
		$action = ($isUpdate ? 'update' : 'insert');
		$keying = array (
			'user' => $this->userMd5,
			'courseId' => $course['id'],
		);
		$conditions = ($isUpdate ? $keying : false);
		$result["course{$course['id']}"] += $keying;
		if (!$this->databaseConnection->$action ($this->settings['database'], $this->tables['courses'], $result["course{$course['id']}"], $conditions)) {
			#!# Use generic error-throwing stuff
			application::utf8Mail ($this->settings['webmaster'], 'System error in Teaching assessment', wordwrap ("{$action} failed for {$this->settings['database']}.{$this->tables['courses']} with data:\n\n" . print_r ($result["course{$course['id']}"], 1)), "From: {$this->settings['webmaster']}");
			echo "<p>There was a problem " . ($isUpdate ? 'updating' : 'inserting') . " the data. The webmaster has been informed.</p>";
		}
		
		# Insert the lecturer data
		#!# Now that the subcourse grouping is in, ideally this would put the insert as a single insertMany query, but there would need to be an updateMany also, which wouldn't be any more efficient
		foreach ($lecturersBySubcourse as $subcourse => $lecturers) {
			foreach ($lecturers as $key => $lecturer) {
				$isUpdate = (!empty ($data['lecturers'][$key]));
				$action = ($isUpdate ? 'update' : 'insert');
				$keying = array (
					'user' => $this->userMd5,
					'lecturerId' => $key,
				);
				$result["lecturer{$key}"] += $keying;
				$result['user'] = $this->userMd5;
				$conditions = ($isUpdate ? $keying : false);
				if (!$this->databaseConnection->$action ($this->settings['database'], $this->tables['lecturers'], $result["lecturer{$key}"], $conditions)) {
					#!# Use generic error-throwing stuff
					application::utf8Mail ($this->settings['webmaster'], 'System error in Teaching assessment', wordwrap ("{$action} failed for {$this->settings['database']}.{$this->tables['lecturers']} with data:\n\n" . print_r ($result["lecturer{$key}"], 1)), "From: {$this->settings['webmaster']}");
					echo "<p>There was a problem " . ($isUpdate ? 'updating' : 'inserting') . " the data. The webmaster has been informed.</p>";
				}
			}
		}
	}
	
	
	# Function to merge in overriden question labels
	#!# Need to add support for e.g. '2021-2022+' to cover future years
	private function overrideQuestionLabels ($currentTable, $definitions, $format = 'form' /* or results */)
	{
		# Loop through each supplied override year, if any
		foreach ($this->settings['overrideQuestionLabels'] as $academicYear => $tables) {
			if ($academicYear != $this->currentAcademicYear) {continue;}	// Skip if not matching
			foreach ($tables as $type => $questions) {
				
				# Format for use in a form
				if ($format == 'form') {
					$table = $this->tables[$type];
					if ($table == $currentTable) {	// Ensure matching table
						foreach ($questions as $questionFieldname => $title) {
							$definitions[$questionFieldname]['title'] = $title;
						}
					}
				}
				
				# Format for use in results
				if ($format == 'results') {
					foreach ($questions as $questionFieldname => $title) {
						$definitions[$questionFieldname] = $title;
					}
				}
			}
		}
		
		# Return the overriden attributes
		return $definitions;
	}
	
	
	# Fieldtrips form
	private function fieldtripsForm ()
	{
		$this->createForm ($this->tables['others']);
	}
	
	
	# Practicals form
	private function practicalsForm ()
	{
		$this->createForm ($this->tables['others']);
	}
	
	
	# Projects form
	private function projectsForm ()
	{
		$this->createForm ($this->tables['others']);
	}
	
	
	# Dissertation form
	private function dissertationForm ()
	{
		$this->createForm ($this->tables['dissertation']);
	}
	
	
	# General form
	private function generalForm ()
	{
		$this->createForm ($this->tables['general'], 'Section');
	}
	
	
	# Others form
	private function createForm ($table, $label = 'Course')
	{
		# Get the data about each module
		foreach ($this->assessing[$this->type] as $key => $module) {
			$title = trim ($module['title']);
			$titles[$module['url']] = $title;
			$modules[$module['url']] = "<a href=\"{$module['link']}\">{$title}</a>";
		}
		
		# Get any data already submitted for this item
		$data[$this->type] = ($this->submissions && (isSet ($this->submissions[$this->type]) && isSet ($this->submissions[$this->type][$this->courseId])) ? $this->submissions[$this->type][$this->courseId] : array ());
		
		# If there is no data submitted for this item, check the totals to ensure the user is not submitting more than they are allowed to assess
		if (!$data[$this->type]) {
			$totalSubmissions = (($this->submissions && isSet ($this->submissions[$this->type])) ? count ($this->submissions[$this->type]) : 0);
			$allowableSubmissions = $this->types[$this->type][$this->userDetails['yeargroup']];
			if ($totalSubmissions == $allowableSubmissions) {
				echo "<p>You cannot submit any more {$this->types[$this->type]['singular']} submissions as you have already submitted the maximum number ({$allowableSubmissions}) allowed.</p>";
				return false;
			}
		}
		
		# If there is no item, force selection (a present 'item' will have been validated by now
		if (empty ($_GET['item'])) {
			echo "<p>Please firstly select which <strong>{$this->types[$this->type]['singular']}</strong> you want to assess:</p>";
			echo application::htmlUl ($modules);
			return false;
		}
		
		# Determine if this is an update
		$isUpdate = (!empty ($data[$this->type]));
		
		# Assign the title
		$title = $titles[$_GET['item']];
		
		# Create the form
		$form = new form (array (
			'databaseConnection' => $this->databaseConnection,
			'displayRestrictions' => false,
			'nullText' => '',
			'formCompleteText' => "Thanks for submitting your assessment. Please now return to the <a href=\"{$this->baseUrl}/\">main overview page</a> (though you can <a href=\"{$_SERVER['REQUEST_URI']}\">edit this submission further</a> if necessary).",
			'linebreaks' => false,
			'div' => 'ultimateform assessments',
			'titleReplacements' => array ('%type' => $this->types[$this->type]['singular']),
			'rows' => 4,
			'cols' => 40,
		));
		
		# Attributes
		$attributes = array (
			#!# Review whether forceAssociative is needed now that it has been improved in application.php v. 1.2.19
			'courseId' => array ('type' => 'select', 'editable' => false, 'values' => array ($this->courseId => $title), 'default' => $this->courseId, 'forceAssociative' => true, 'title' => $label, ),
			
			# Attributes for $this->tables['others'] table
			'q2astimulating' => array ('heading' => array ('' => 'To what extent do you feel that ...')),
			
			# Attributes for $this->tables['general'] table
			'q1library' => array ('heading' => array (3 => 'Learning resources'), ),
			'q4confidence' => array ('heading' => array (3 => 'Personal development'), ),
		);
		
		# Databind the form
		$attributes = $this->overrideQuestionLabels ($table, $attributes);
		$form->dataBinding (array (
			'database' => $this->settings['database'],
			'table' => $table,
			'data' => $data[$this->type],
			'exclude' => array ('id','user', 'timestamp'),
			'attributes' => $attributes,
		));
		
		# Send a backup copy to the administrator
		$subject = "Teaching assessment: {$this->user} - {$this->userDetails['yeargroup']} - {$this->type} - {$_GET['item']}" . ($isUpdate ? ' (update)' : '');
		$form->setOutputEmail ($this->settings['webmaster'], $this->settings['webmaster'], $subject, "From: {$this->settings['webmaster']}");
		
		# Show the results on screen
		$form->setOutputScreen ();
		
		# Obtain the result
		if (!$result = $form->process ()) {return false;}
		
		# Add in the reference fields
		$result['user'] = $this->userMd5;
		
		# Insert the data
		$action = ($isUpdate ? 'update' : 'insert');
		$keying = array (
			'user' => $this->userMd5,
			'courseId' => $this->courseId,
		);
		$conditions = ($isUpdate ? $keying : false);
		$result += $keying;
		if (!$this->databaseConnection->$action ($this->settings['database'], $table, $result, $conditions)) {
			#!# Use generic error-throwing stuff
			$errorMessage = "{$action} failed for {$this->settings['database']}.{$table} with data:\n\n" . print_r ($result, 1);
			$headers = "From: {$this->settings['webmaster']}";
			application::utf8Mail ($this->settings['webmaster'], 'System error in Teaching assessment', wordwrap ($errorMessage), $headers);
			echo "\n<p>There was a problem " . ($isUpdate ? 'updating' : 'inserting') . " the data. The webmaster has been informed.</p>";
		}
	}
	
	
	# Function to show the results
	private function showResults ($csvMode = false)
	{
		/*
			SECURITY MODEL:
			- Students should never be able to see any results
			- Results are only viewable after the submission period closes (except for admins)
			- Webmaster, HoD, HoD's Secretary should see ALL results submitted
			- Organisers (Undergraduate Director(s), Undergraduate Office Administrator) should see all courses & other bits but NOT the lecturer assessments
			- Lecturers (including Postgraduate lecturers) should be able to see the results from their own lecturer assessment(s), AND the course assessment for each such course they are teaching on
			- Certain people (in the 'denyResults' list are prevented from viewing their results
			Course Co-ordinators also need access to each course, but they are almost always going to be Lecturers on that course
		*/
		
		# Results are only viewable after the submission period closes (except for admins)
		if (!$this->settings['allowViewingDuringSubmitting']) {
			if (!$this->resultsViewable ()) {
				$dateFormatted = date ('jS F Y', strtotime ($this->settings['closing'] . ' GMT') + 1);
				$html = "\n<p class=\"warning\">Results are not viewable until {$dateFormatted}.</p>";
				return $html;
			}
		}
		
		# End if the user is in the list of denied users
		if (in_array ($this->user, $this->settings['denyResults'])) {
			return "\n<p class=\"warning\">Results are not yet viewable.</p>";
		}
		
		# Get all courses and lecturers being assessed this year
		$courses = $this->getCoursesLecturedAssessedThisYear ();
		$lecturers = $this->getLecturersAssessedThisYear ();
		
		# Merge the lists
		$courseDetails = array_merge ($courses, $lecturers);
		
		# End if no results
		#!# This message should be more specific
		if (!$courseDetails) {
			$html  = "\n<p>No results are available to you for the selected year.</p>";
			return $html;
		}
		
		# Regroup by type
		$courseDetailsByGroup = application::regroup ($courseDetails, 'type', $removeGroupColumn = true);
		
		# Assign the results data
		$submissionsByCourseIdByGroup = array ();
		$questionsByGroup = array ();
		$fieldsByGroup = array ();
		$entrantsByGroup = array ();
		foreach ($courseDetailsByGroup as $group => $courses) {
			
			# Determine which database table to use
			#!# Move to a central registry
			switch ($group) {
				case 'courses':
				case 'lecturers':
				case 'general':
					$table = $this->tables[$group];
					break;
				default:
					$table = $this->tables['others'];
			}
			
			# Get the submissions for each course
			$submissionsByCourseIdByGroup[$group] = $this->getSubmissionsByCourseId ($group, $courses, $table);
			
			# Get the headings for each table
			$questionsByGroup[$group] = $this->databaseConnection->getHeadings ($this->settings['database'], $table);
			$questionsByGroup[$group] = $this->overrideQuestionLabels ($group, $questionsByGroup[$group], 'results');

			# Get the fields for this table
			$fieldsByGroup[$group] = $this->databaseConnection->getFields ($this->settings['database'], $table);
			
			# Determine the entrants for each course
			$entrantsByGroup[$group] = $this->getEntrants ($courses, $group);
		}
		
		# Show the results, subclassing to the result viewer
		require_once ('assessmentsResults.php');
		$assessmentsResults = new assessmentsResults ($this->settings, $this->baseUrl, $this->userIsAdministrator, $this->types, $courseDetailsByGroup, $questionsByGroup, $fieldsByGroup, $submissionsByCourseIdByGroup, $entrantsByGroup, $this->currentAcademicYear, $csvMode);
		$html = $assessmentsResults->getHtml ();
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to determine if the results are viewable
	private function resultsViewable ()
	{
		# Admins can always see the results
		if ($this->userDetails['type'] == 'administrator') {
			return true;
		}
		
		# Viewable if after the closing time
		if (time () > strtotime ($this->settings['closing'] . ' GMT')) {
			return true;
		}
		
		# Otherwise, the results are not viewable
		return false;
	}
	
	
	# Function to get the courses being lectured being assessed this year
	private function getCoursesLecturedAssessedThisYear ()
	{
		# Construct the query
		$query = "SELECT
			courses.*,
			CONCAT(courses.year,'_',courses.yeargroup,'_',courses.type,'_',courses.url) AS href,
			CONCAT(UPPER(courses.yeargroup),IF(courses.paper REGEXP '[0-9]',CONCAT(' Paper ',courses.paper,':'),''),' ',courses.title) as heading,
			LPAD(IF(courses.paper REGEXP '[0-9]',courses.paper,9999),50,'0') AS paperNumberNatsorted	/* Aim here is to get numeric papers first in a natsorted order, followed by 9999 for others which then get order by id so that the imported order is retained for those non-numeric papers) */
			FROM
				" . ($this->userDetails['type'] == 'lecturer' ? "{$this->settings['database']}.lecturers," : '') . "
				{$this->settings['database']}.courses
			WHERE courses.year = '{$this->currentAcademicYear}'
			" . ($this->userDetails['type'] == 'lecturer' ? "
				AND lecturers.lecturer = '{$this->user}'
				AND lecturers.course = courses.url			/* These three basically act as the combined key between lecturers and courses */
				AND lecturers.year = courses.year
				AND lecturers.yeargroup = courses.yeargroup
				
			" : '') . "
			ORDER BY year, yeargroup, type, paperNumberNatsorted, id, title
		;";
		
		# Get the data
		#!# Some sort of error handling needed that detects the difference between database failure and no results
		$courses = $this->databaseConnection->getData ($query);
		
		# Return the list
		return $courses;
	}
	
	
	# Function to get the lecturers being assessed this year
	private function getLecturersAssessedThisYear ()
	{
		#!# LOWER(yeargroup) inconsistency in data needs to be fixed
		#!# Use of globalPeopleDatabase needs to be eliminated in favour of an API call
		$query = "SELECT
			lecturers.*,
			CONCAT(lecturers.year,'_',LOWER(lecturers.yeargroup),'_',lecturers.course,'_',lecturers.lecturer) AS href,
			CONCAT(UPPER(lecturers.yeargroup),IF(courses.paper REGEXP '[0-9]',CONCAT(' Paper ',courses.paper,':'),''),' ',courses.title,IF(lecturers.subCourseName IS NOT NULL,CONCAT(' (', lecturers.subCourseName,')'),''),' - ',{$this->settings['globalPeopleDatabase']}.people.forename,' ',{$this->settings['globalPeopleDatabase']}.people.surname) AS heading,
			'lecturers' AS type,
			LPAD(courses.paper,100,'0') AS paperNumberNatsorted
			FROM
				{$this->settings['database']}.lecturers,
				{$this->settings['database']}.courses,
				{$this->settings['globalPeopleDatabase']}.people
			WHERE
				lecturers.year = '{$this->currentAcademicYear}'
				AND
					lecturers.course = courses.url			/* These three basically act as the combined key between lecturers and courses */
					AND lecturers.year = courses.year
					AND lecturers.yeargroup = courses.yeargroup
				AND lecturers.lecturer = {$this->settings['globalPeopleDatabase']}.people.username
				" . ((($this->userDetails['type'] == 'lecturer') || ($this->userDetails['type'] == 'organiser')) ? "AND lecturers.lecturer = '{$this->user}'" : '') . "
			ORDER BY year, yeargroup, type, paperNumberNatsorted, course, subcourseId, lecturer
		;";
		
		# Get the data
		#!# Some sort of error handling needed that detects the difference between database failure and no results
		$lecturers = $this->databaseConnection->getData ($query);
		
		# For each course having a subcourse identifier, state the fields to exclude
		foreach ($lecturers as $index => $lecturer) {
			if (isSet ($lecturer['subcourseId']) && $lecturer['subcourseId']) {
				$lecturers[$index]['excludeQuestions'] = $this->subcourseModeExcludeFields;
			}
		}
		
		# Return the list
		return $lecturers;
	}
	
	
	# Function to determine the entrants on each course
	private function getEntrants ($results, $group)
	{
		# Determine the entrants
		$entrants = array ();
		foreach ($results as $index => $course) {
			$entrants[$index] = false;
			if ($group != 'lecturers') {
				$entrantsQuery = "SELECT
						COUNT(entries.id) as entrants
						FROM {$this->settings['database']}.entries, {$this->settings['database']}.courses
						WHERE
							courses.id = '{$course['id']}'
							AND entries.year = courses.year		/* These three basically act as the combined key between entries and courses */
							AND entries.yeargroup = courses.yeargroup
							AND entries.paper = courses.paper
						GROUP BY courses.id
						;";
				#!# Can return false rather than a count
				$entrantsResult = $this->databaseConnection->getOneField ($entrantsQuery, 'entrants');
				$entrants[$index] = $entrantsResult;
			}
		}

		# Return the entrants
		return $entrants;
	}
	
	
	# Function to get the submissions for each course
	private function getSubmissionsByCourseId ($group, $results, $table)
	{
		#!# Inefficient version - needs to be fixed as runs SQL statements in a loop; but version afterwards fails to get the lecturer results for some reason
		# Get the submissions for each course
		$submissions = array ();
		foreach ($results as $index => $course) {
			#!# Needs to be converted to prepared statements
			$query = "SELECT * FROM {$this->settings['database']}.{$table} WHERE " . ($group == 'lecturers' ? 'lecturerId' : 'courseId') . " = '{$course['id']}';";
			$submissions[$course['id']] = $this->databaseConnection->getData ($query);
		}
		return $submissions;
		
		# Compile a list of courses
		$courseIds = array ();
		foreach ($results as $index => $course) {
			$courseIds[] = $course['id'];
		}
		
		# Get the data
		#!# Needs to be converted to prepared statements
		$query = "SELECT * FROM {$this->settings['database']}.{$table} WHERE " . ($group == 'lecturers' ? 'lecturerId' : 'courseId') . " IN ('" . implode ("','", $courseIds) . "') ORDER BY courseId;";
		$submissions = $this->databaseConnection->getData ($query);
		
		# Regroup by courseId
		$submissions = application::regroup ($submissions, 'courseId', false);
		
		# Add in courses with no matches
		foreach ($courseIds as $courseId) {
			if (!isSet ($submissions[$courseId])) {
				$submissions[$courseId] = array ();
			}
		}
		
		// application::dumpData ($submissions);
		
		# Return the result
		return $submissions;
	}
	
	
	# Feedback form
	public function feedback ($id_ignored = NULL, $error_ignored = NULL, $echoHtml = true)
	{
		# Add text
		echo "\n<p class=\"warning\"><em>Please do not use this form for course feedback - this page is only for problems/suggestions on the assessment facility itself so that we can make improvements to it or fix problems.</em></p>";
		echo "\n<p class=\"warning\"><em>Submissions made through <strong>this</strong> form are <strong>not</strong> anonymous.</em></p>";
		
		# Run the standard function
		parent::feedback ($id_ignored, $error_ignored, $echoHtml);
	}
	
	
	# Function to work out which (if any) courses a user is lecturing
	private function getLecturers ($courseUrl)
	{
		# Get the data
		#!# Use of globalPeopleDatabase needs to be eliminated in favour of an API call
		$query = "SELECT
			lecturers.*,
			CONCAT({$this->settings['globalPeopleDatabase']}.people.forename,' ',{$this->settings['globalPeopleDatabase']}.people.surname) AS name
			FROM
				{$this->settings['database']}.lecturers,
				{$this->settings['globalPeopleDatabase']}.people
			WHERE
				lecturers.year = '{$this->currentAcademicYear}'
				AND lecturers.yeargroup = '{$this->userDetails['yeargroup']}'
				AND lecturers.course = '" . $this->databaseConnection->escape ($courseUrl) . "'
				AND lecturers.lecturer = {$this->settings['globalPeopleDatabase']}.people.username
			ORDER BY subcourseId,id
		;";
		$lecturers = $this->databaseConnection->getData ($query, "{$this->settings['database']}.lecturers");
		
		# Return the data;
		return $lecturers;
	}
	
	
	# Function to get courses being assessed by the student
	private function getAssessing ()
	{
		# Get the data
		#!# "paper IN" here should really be using url rather than the paper, which is really just intended for sorting purposes only
		$query = "SELECT
				*
			FROM {$this->settings['database']}.courses
			WHERE
				    `year` = '{$this->currentAcademicYear}'
				AND yeargroup = '{$this->userDetails['yeargroup']}'
				AND (
					entries = '1'
					OR paper IN (
						SELECT paper FROM {$this->settings['database']}.entries
						WHERE
							crsid = '{$this->user}'
							AND `year` = '{$this->currentAcademicYear}'
							AND yeargroup = '{$this->userDetails['yeargroup']}'
					)
				)
			ORDER BY LPAD(paper,25,'0'), type, title, entries	/* 25 should be safe to get full sorting! */
		;";
		$data = $this->databaseConnection->getData ($query, "{$this->settings['database']}.courses");
		
		# Add the links in
		foreach ($data as $key => $attributes) {
			$module = ($attributes['type'] == 'courses' ? '' : "{$attributes['type']}/");
			$data[$key]['link'] = "{$this->baseUrl}/{$attributes['year']}/{$attributes['yeargroup']}/{$module}{$attributes['url']}/";
		}
		
		# Regroup the data
		$data = application::regroup ($data, 'type');
		
		# Return the data
		return $data;
	}
	
	
	# Get the submissions for this user
	private function getSubmissionsOfCurrentUser ()
	{
		# Start a master array to hold all the user's feedback
		$allData = array ();
		
		# Define the table relations
		$relations = array (
			$this->tables['others'] => 'course',
			$this->tables['courses'] => 'course',
			$this->tables['lecturers'] => 'lecturer',
			$this->tables['dissertation'] => 'course',
			$this->tables['general'] => 'course',
		);
		
		# Get each data set
		foreach ($relations as $source => $target) {
			$query = "SELECT
					{$source}.*,
					{$target}s.year,
					" . ($target == 'lecturer' ? "'lecturers' as type" : "{$target}s.type") . "
				FROM
				{$this->settings['database']}.{$source}, {$this->settings['database']}.{$target}s
				WHERE
					user = '{$this->userMd5}'
					AND {$source}.{$target}Id = {$target}s.id
					AND {$target}s.year = '{$this->currentAcademicYear}'
			;";
			$data = $this->databaseConnection->getData ($query);
			
			# Reorganise with the courseId as the key
			$feedback = array ();
			foreach ($data as $index => $submission) {
				$key = $submission["{$target}Id"];
				$feedback[$key] = $submission;
			}
			$allData += application::regroup ($feedback, 'type');
		}
		
		# Return the data
		return $allData;
	}
	
	
	# Function to show assessing
	private function submissionSystem ()
	{
		# End if no assessing
		if (!$this->assessing) {
			#!# Report to admin
			//return "\n<p><em>Nothing is available to assess; there may have been a system error.</em></p>";
			return false;
		}
		
		//application::dumpData ($this->assessing, $hide = true);
		
		# Start this section
		$html  = "\n<h2>Evaluations</h2>";
		
		# Ensure the facility is open
		if (!$this->facilityIsOpen ($html)) {return $html;}
		
		# Create a list
		$html .= "\n<p>You are registered on the following courses or other modules and are asked kindly to evaluate these.<br />(If the list is not fully correct, please <a href=\"{$this->baseUrl}/feedback.html\">contact the Webmaster</a>.)</p>";
		
		# Add introduction text if required
		if ($this->settings['introductionHtml']) {
			$html .= "\n<div class=\"graybox\">";
			$html .= $this->settings['introductionHtml'];
			$html .= "\n</div>";
		}
		
		# Loop through each type to create the list
		foreach ($this->types as $type => $attributes) {
			
			# Skip if not being assessed for this year group
			if (!array_key_exists ($this->userDetails['yeargroup'], $attributes)) {continue;}
			
			# Skip if the user has none of this type (courses/fieldtrips/etc.) available to them
			if (!isSet ($this->assessing[$type])) {continue;}
			if (!$this->assessing[$type]) {continue;}
			
			# Start a list
			$list = array ();
			
			# A yeargroup type set to 0 means unlimited
			if (($attributes[$this->userDetails['yeargroup']] == 0) || ($attributes[$this->userDetails['yeargroup']] == count ($this->assessing[$type]))) {
				foreach ($this->assessing[$type] as $key => $course) {
					$alreadySubmitted = (isSet ($this->submissions[$type][$key]));
					$list[$key] = "<a" . ($alreadySubmitted ? ' class="submitted"' : '') . " href=\"{$course['link']}\">{$course['title']}" . ($alreadySubmitted ? ' [Edit further]' : '') . "</a>" . (($course['paper'] && is_numeric ($course['paper'])) ? " <span class=\"comment\">(paper {$course['paper']})</span>" : '');
				}
			} else {
				
				# Determine the number of submission slots available
				$totalAvailable = count ($this->assessing[$type]);
				
				# If there have been submissions, list these instead, then show the remaining generic slots
				$totalSubmitted = 0;
				if (isSet ($this->submissions[$type]) && ($this->submissions[$type])) {
					$totalAvailable = $totalAvailable - $totalSubmitted;
					$totalSubmitted = count ($this->submissions[$type]);
					foreach ($this->submissions[$type] as $key => $course) {
						$list[] = "<a class=\"submitted\" href=\"{$this->assessing[$type][$key]['link']}\">{$this->assessing[$type][$key]['title']} [Edit further]</a>";
					}
				}
				
				# For limited types, construct the list
				$startAt = $totalSubmitted + 1;
				for ($i = $startAt; $i <= $attributes[$this->userDetails['yeargroup']]; $i++) {
					$list[] = "<a href=\"{$this->baseUrl}/{$this->currentAcademicYear}/". strtolower ($this->userDetails['yeargroup']) . "/{$type}/\">". ucfirst ($this->types[$type]['singular']) . " #{$i}</a>";
				}
			}
			
			# Construct the HTML
			$html .= "\n<h3>{$attributes['name']}</h3>";
			$html .= "\n" . application::htmlUl ($list, false, 'listing');
		}
		
		# Note anonymity
		$html .= "\n<br /><br /><p><strong>All submissions are anonymous</strong> - your Raven identification is used only for security and to enable you to update existing submissions. Those analysing the results will <strong>not</strong> be able to obtain your identity (a token matching system is used to avoid storing usernames against submissions).</p>";
		
		# Add a feedback link for the system itself
		$html .= "<p class=\"feedback\">(The Webmaster would also welcome any <a href=\"{$this->baseUrl}/feedback.html\">feedback or ideas about this course assessment system</a> itself, if you have any.)</p>";
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to get submission rates
	public function rates ()
	{
		# Get all the users in the current academic year
		$query = "SELECT
			DISTINCT
				MD5(crsid) AS crsidMd5,
				crsid,
				forename,
				surname,
				yeargroup,
				COUNT(crsid) AS 'Course entries',
				colleges.college
			FROM assessments.entries
			LEFT OUTER JOIN {$this->settings['globalPeopleDatabase']}.people ON assessments.entries.crsid = {$this->settings['globalPeopleDatabase']}.people.username
			LEFT OUTER JOIN {$this->settings['globalPeopleDatabase']}.colleges ON {$this->settings['globalPeopleDatabase']}.college__JOIN__people__colleges__reserved = {$this->settings['globalPeopleDatabase']}.colleges.id
			WHERE assessments.entries.year = '{$this->currentAcademicYear}'
			GROUP BY crsid, yeargroup	/* yeargroup to avoid 'Expression #5 of SELECT list is not in GROUP BY clause' */
			ORDER BY yeargroup, surname, forename
		;";
		if (!$users = $this->databaseConnection->getData ($query)) {
			echo "\n<p>No users have been found.</p>";
			return false;
		}
		
		# Get the counts and rearrange them as user => total
		$query = "SELECT
				user,
				COUNT(assessments.{$this->tables['courses']}.id) as total
			FROM assessments.{$this->tables['courses']}
			LEFT OUTER JOIN assessments.courses ON assessments.{$this->tables['courses']}.courseId = assessments.courses.id
			WHERE assessments.courses.year = '{$this->currentAcademicYear}'
			GROUP BY user
			ORDER BY total;";
		if (!$data = $this->databaseConnection->getData ($query)) {
			echo "\n<p>No data has been found.</p>";
			return false;
		}
		$totals = array ();
		foreach ($data as $index => $item) {
			$userMd5 = $item['user'];
			$total = $item['total'];
			$totals[$userMd5] = $total;
		}
		
		# Loop through each user, maintaining that order (which is by surname) and show their submissions total
		$results = array ();
		$totalSubmissions = array ();
		$studentsNotResponded = array ();
		$studentsSufficientlyResponded = array ();
		$responsesThreshold = 3; // 3 is a good enough approximation for 'most filled in'
		foreach ($users as $index => $user) {
			$username = $user['crsid'];
			$crsidMd5 = $user['crsidMd5'];
			$college = $user['college'];
			$results[$username] = $user;	// e.g. forename, surname
			$submissions = (isSet ($totals[$crsidMd5]) ? $totals[$crsidMd5] : 0);
			$yearGroup = $user['yeargroup'];
			if ($submissions) {
				if (!isSet ($totalSubmissions[$yearGroup])) {
					$totalSubmissions[$yearGroup] = 0;		// Initialise array key
				}
				$totalSubmissions[$yearGroup]++;
				if ($submissions >= $responsesThreshold) {
					$studentsSufficientlyResponded[$username] = $submissions;
				}
			} else {
				$studentsNotResponded[$yearGroup][$college][] = $user['forename'] . ' ' . $user['surname'] . ' <' . $username . '>';
			}
			$results[$username]['Course/practical submissions'] = $submissions;
			switch (true) {
				case (!$results[$username]['Course/practical submissions']):
					$results[$username]['Course/practical submissions'] = "<span style=\"color: red\"><strong>{$results[$username]['Course/practical submissions']}</strong></span>";
					$results[$username]['crsid'] = "<span style=\"color: red\"><strong>{$results[$username]['crsid']}</strong></span>";
					break;
				/*	// #!# Reinstate this when the use of assessments.courses.entries = 1 is scrapped
				case ($results[$username]['Course/practical submissions'] >= $results[$username]['Course entries']):
					$results[$username]['Course/practical submissions'] = "<span style=\"color: green\"><strong>{$results[$username]['Course/practical submissions']}</strong></span>";
					break;
				default:
					$results[$username]['Course/practical submissions'] = "<span style=\"color: orange\"><strong>{$results[$username]['Course/practical submissions']}</strong></span>";
				*/
			}
			unset ($results[$username]['crsidMd5']);
			unset ($results[$username]['Course entries']);	// #!# Remove this when the use of assessments.courses.entries = 1 is scrapped
		}
		
		# Pick a random person
		$randomUsername = array_rand ($studentsSufficientlyResponded);
		$winningStudent = $results[$randomUsername];
		
		# Regroup by yeargroup
		$results = application::regroup ($results, 'yeargroup', true);
		
		# Compute the summaries
		$summaryTotals = array ();
		foreach ($results as $yearGroup => $resultSet) {
			$totalStudents = count ($resultSet);
			$rate = round (($totalSubmissions[$yearGroup] / $totalStudents) * 100, 1);
			$summaryTotals[$yearGroup] = "<strong>{$totalSubmissions[$yearGroup]}/{$totalStudents}</strong> students (<strong>{$rate}%</strong>)";
		}
		
		# Create a link list
		$linkList = array ();
		foreach ($results as $yearGroup => $resultSet) {
			$yearGroupMoniker = strtolower ($yearGroup);
			$linkList[] = "<a href=\"#{$yearGroupMoniker}\">Part {$yearGroup}</a> - {$summaryTotals[$yearGroup]}";
		}
		
		# Compile the HTML
		$html  = "\n<p>The following shows the list of submissions for the current year against each user that is registered to have some courses:</p>";
		$html .= "\n<p>Jump to:</p>";
		$html .= application::htmlUl ($linkList, 0, 'small');
		$html .= "\n<p>The <strong>closing date for submissions</strong> is currently set in the <a href=\"{$this->baseUrl}/settings.html\">settings</a> to: <strong>" . date ('jS F Y', strtotime ($this->settings['closing'] . ' GMT') + 1) . '</strong>.</p>';
		$html .= "\n" . '<!-- Enable table sortability: --><script language="javascript" type="text/javascript" src="/sitetech/sorttable.js"></script>';
		foreach ($results as $yearGroup => $resultSet) {
			$yearGroupMoniker = strtolower ($yearGroup);
			$html .= "<h3 id=\"{$yearGroupMoniker}\">Part {$yearGroup}</h3>";
			$html .= "\n<p>Submissions: {$summaryTotals[$yearGroup]} have made at least one submission.</p>";
			$html .= "\n" . '<div class="graybox">';
			$html .= "\n\t<p>The following Part {$yearGroup} students have not responded to the survey yet:</p>";
			$collegeLists = array ();
			foreach ($studentsNotResponded[$yearGroup] as $college => $students) {
				$collegeLists[$college] = '<strong>' . htmlspecialchars ($college) . '</strong>: ' . htmlspecialchars (implode (', ', $students));
			}
			natsort ($collegeLists);
			$html .= application::htmlUl ($collegeLists, 1, 'small');
			$html .= "\n" . '</div>';
			$html .= application::htmlTable ($resultSet, array (), 'lines small compressed sortable" id="' . $yearGroupMoniker, false, true, true);
		}
		
		# Show the winning student
		$html .= "\n<h3>Random student who has completed at least {$responsesThreshold} course feedbacks</h3>";
		$html .= "\n<p><strong>" . htmlspecialchars ("{$winningStudent['forename']} {$winningStudent['surname']}") . "</strong> &lt;<strong>{$winningStudent['crsid']}</strong>&gt; (Part {$winningStudent['yeargroup']})</p>";
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to help import assessment details
	public function import ()
	{
		# Get the undergraduate yeargroups
		$undergraduateYearGroups = array_merge (array ('Not applicable'), array_values ($this->undergraduateYearGroupsForCurrentAcademicYear ()));
		
		# Create a form
		$form = new form (array (
			'formCompleteText' => false,
			'reappear'	=> true,
		));
		$form->radiobuttons (array (
			'name'		=> 'action',
			'title'		=> 'Action',
			'required'	=> true,
			'values'	=> array (
				'test'		=> 'Test',
				'import'	=> "Import, clearing any current entries for the specified group this year ({$this->currentAcademicYear})"
			),
			'default'	=> 'test',
		));
		$form->radiobuttons (array (
			'name'		=> 'type',
			'title'		=> 'Type',
			'values'	=> array (
				'courses'		=> 'Courses (fields: yeargroup,type,paper,url,title)',
				'lecturers'		=> 'Lecturers (fields: yeargroup,course,subcourseId,subcourseName,lecturer)',
				'entries-ia'	=> 'Entries: IA (fields: crsid,1,2,3...)',
				'entries-ib'	=> 'Entries: IB (fields: crsid,1,2,3...)',
				'entries-ii'	=> 'Entries: II (fields: crsid,1,2,3...)',
			),
			'required'  => true,
		));
		$form->textarea (array (
			'name'		=> 'data',
			'title'		=> 'Paste in your spreadsheet contents, including the headers',
			'required'	=> true,
			'rows'		=> 15,
			'cols'		=> 120,
		));
		
		# Do checks on the pasted data
		require_once ('csv.php');
		if ($unfinalisedData = $form->getUnfinalisedData ()) {
			if ($unfinalisedData['type'] && $unfinalisedData['data']) {
				
				# Arrange the data
				$data = csv::tsvToArray ($unfinalisedData['data']);
				
				# Ensure there is some data
				if (count ($data) < 1) {
					$form->registerProblem ('data', 'There must be at least one line of data in the pasted spreadsheet block.');
				}
				
				# Ensure the headings are all present
				$expectedHeaders = array (
					'courses'	=> array ('yeargroup', 'type', 'paper', 'url', 'title'),
					'lecturers' => array ('yeargroup', 'course', 'subcourseId', 'subcourseName', 'lecturer', ),
					#!# Needs to have basic checking for crsid plus additional columns
					// entries-ia/ib/ic - these have only CRSIDs and course references, which cannot be dynamically checked
				);
				$type = $unfinalisedData['type'];
				if (isSet ($expectedHeaders[$type])) {
					$headersPresent = array_keys ($data[0]);
					if ($headersPresent !== $expectedHeaders[$type]) {
						$form->registerProblem ('headers', 'The headers in the pasted spreadsheet block must be exactly <strong><tt>' . implode ('</tt>, <tt>', $expectedHeaders[$type]) . '</tt></strong>');
					}
				}
				
				#!# At this stage, the system should check that all submitted CRSIDs are in the {$this->settings['globalPeopleDatabase']}.people database. Although these will be picked up on a subsequent page load, the importer ought to do that check.
				#!# Also, the system should check that, once courses and lecturer data is present, that the student data uses the same URL monikers for the courses, complaining if there is a mismatch.
			}
		}
		
		# Process the form or end
		if (!$result = $form->process ()) {return false;}
		
		# Convert the data into a CSV structure
		$data = csv::tsvToArray ($result['data']);
		
		# Process to a list of entries
		$insert = array ();
		$table = $result['type'];
		foreach ($data as $itemKey => $item) {
			
			# Assemble the data in the right structure for this type
			switch ($result['type']) {
				
				# Courses
				case 'courses':
					$insert[] = array (
						'year'		=> $this->currentAcademicYear,
						'yeargroup'	=> strtolower ($item['yeargroup']),
						'type'		=> $item['type'],
						'url'		=> $item['url'],
						'title'		=> $item['title'],
						'entries'	=> '0',
						'paper'		=> $item['paper'],
					);
					break;
					
				# Lecturers
				case 'lecturers':
					$insert[] = array (
						'year'			=> $this->currentAcademicYear,
						'yeargroup'		=> strtoupper ($item['yeargroup']),
						'course'		=> $item['course'],			// This is the URL format, not paper ID - needs to be standardised with entries below
						'subcourseId'	=> $item['subcourseId'],
						'subcourseName'	=> $item['subcourseName'],
						'lecturer'		=> $item['lecturer'],
					);
					break;
					
				# Entries
				case 'entries-ia':
				case 'entries-ib':
				case 'entries-ii':
					$crsid = strtolower ($item['crsid']);
					$yeargroup = strtoupper (str_replace ('entries-', '', $result['type']));
					foreach ($item as $paper => $arbitraryString) {
						if ($paper == 'crsid') {continue;}
						if (strlen ($arbitraryString)) {	// Doesn't matter what the text is in the cell
							$insert[] = array (
								'crsid'		=> $crsid,
								'year'		=> $this->currentAcademicYear,
								'yeargroup'	=> $yeargroup,
								'paper'		=> $paper,
							);
						}
					}
					$table = 'entries';
					$totalCourses = count ($item) - 1;
					break;
			}
		}
		
		# For a test, show the data, then end
		if ($result['action'] == 'test') {
			$rows = count ($insert);
			#!# $students is only relevant to the student data imports, not courses or lecturers, so it shouldn't be shown in those cases
			$students = count ($data);
			echo "\n<p><strong>There are {$rows} rows, which is for {$students} students:</strong></p>";
			echo application::htmlTable ($insert);
			return true;
		}
		
		# Delete any existing entries
		$query = "DELETE FROM {$table} WHERE year = '{$this->currentAcademicYear}'" . ($table == 'entries' ? " AND yeargroup = '{$yeargroup}'" : '') . ';';
		$this->databaseConnection->query ($query);
		
		# Insert the data
		if (!$result = $this->databaseConnection->insertMany ($this->settings['database'], $table, $insert)) {
			echo "\n<p class=\"warning\">Error:</p>";
			application::dumpData ($this->databaseConnection->error ());
			return false;
		}
		
		# Confirm success<br>
		echo "\n<div class=\"graybox\">";
		echo "\n\t<p class=\"success\">{$this->tick} The data has been successfully imported.</p>";
		echo "\n</div>";
	}
	
	
	# Settings form
	public function settings ($dataBindingSettingsOverrides = array ())
	{
		$dataBindingSettingsOverrides = array (
			'int1ToCheckbox' => true,
		);
		parent::settings ($dataBindingSettingsOverrides);
	}
	
	
	# User switcher lookup
	public function userSwitcherUsers ()
	{
		return $this->getUsersByYeargroup ();
	}
	
	
	# User switcher callback on change
	public function userSwitcherOnSwitch ($newUser)
	{
		# Re-run the main pre-actions, to regenerate the user's credentials
		$this->mainPreActions ();
	}
	
	
	# Function to get users by yeargroup
	private function getUsersByYeargroup ()
	{
		# Get the users
		$query = "
			SELECT
				DISTINCT crsid,
				CONCAT_WS(' ', people.forename, people.surname) AS name,
				yeargroup
			FROM assessments.entries
			LEFT OUTER JOIN {$this->settings['globalPeopleDatabase']}.people ON assessments.entries.crsid = {$this->settings['globalPeopleDatabase']}.people.username
			WHERE entries.year = '{$this->currentAcademicYear}'
			ORDER BY yeargroup, crsid
		;";
		#!# Can't seem to get out by username as key, using "{$this->settings['globalPeopleDatabase']}.people"
		$users = $this->databaseConnection->getData ($query);
		
		# Regroup by yeargroup
		$usersByYeargroup = array ();
		foreach ($users as $user) {
			$yeargroup = $user['yeargroup'];
			$username = $user['crsid'];
			$usersByYeargroup[$yeargroup][$username] = $username . ' - ' . $user['name'];
		}
		
		# Return the list
		return $usersByYeargroup;
	}
}

?>

<?php


# Assessments results subclass
class assessmentsResults
{
	# Class properties
	private $html = '';
	
	# Settings
	
	
	# Constructor
	public function __construct ($settings, $baseUrl, $userIsAdministrator, $types, $courseDetailsByGroup, $questionsByGroup, $fieldsByGroup, $submissionsByCourseIdByGroup, $entrantsByGroup, $currentAcademicYear, $csvMode = false)
	{
		# Global the settings
		$this->baseUrl = $baseUrl;
		$this->types = $types;
		$this->currentAcademicYear = $currentAcademicYear;
		$this->userIsAdministrator = $userIsAdministrator;
		
		# Debugging
		//echo '---'; application::dumpData ($courseDetailsByGroup);
		//echo '---'; application::dumpData ($questionsByGroup);
		//echo '---'; application::dumpData ($fieldsByGroup);
		//echo '---'; application::dumpData ($submissionsByCourseIdByGroup);
		//echo '---'; application::dumpData ($entrantsByGroup);
		
		# Get the results by group
		$resultsByGroup = $this->compileDataByGroup ($courseDetailsByGroup, $questionsByGroup, $fieldsByGroup, $submissionsByCourseIdByGroup, $entrantsByGroup);
		
		# Convert all the results from the courses to HTML
		if ($csvMode) {
			$html  = $this->resultsPageTabulated ($resultsByGroup);
		} else {
			$html  = $this->resultsPageHtml ($resultsByGroup, $courseDetailsByGroup, $settings['includePieCharts'], $settings['pieChartWidth'], $settings['pieChartHeight'], $settings['piechartStub']);
		}
		
		# Register the HTML
		$this->html = $html;
	}
	
	
	# Function to return the HTML
	public function getHtml ()
	{
		# Return the HTML
		return $this->html;
	}
	
	
	# Wrapper function to compile the result data by group
	private function compileDataByGroup ($courseDetailsByGroup, $questionsByGroup, $fieldsByGroup, $submissionsByCourseIdByGroup, $entrantsByGroup)
	{
		# Get the results by group
		$resultsByGroup = array ();
		foreach ($courseDetailsByGroup as $group => $courses) {
			$resultsByGroup[$group] = $this->compileData ($group, $courses, $questionsByGroup[$group], $fieldsByGroup[$group], $submissionsByCourseIdByGroup[$group], $entrantsByGroup[$group]);
		}
		
		# Return the compiled data
		return $resultsByGroup;
	}
	
	
	# Function to organise the data
	private function compileData ($group, $courses, $questions, $fields, $submissionsByCourseId, $entrants)
	{
		# Define empty responses to be omitted from results output
		$emptyResponses = array ('', '-', 'n/a', 'n.a', '.');
		
		# Loop through each course
		$data = array ();
		foreach ($courses as $index => $course) {
			
			# Define the link
			$data[$index]['href'] = $course['href'];
			
			# Define the title
			$data[$index]['heading'] = $course['heading'];
			
			# Define the response rates for this course
			$data[$index]['entrants'] = $entrants[$index];
			
			# Define the response rate data items
			$data[$index]['submissions'] = (isSet ($submissionsByCourseId[$course['id']]) ? count ($submissionsByCourseId[$course['id']]) : 0);
			#!# Not sure why the entrants rate might not be present
			$data[$index]['responseRate'] = (($data[$index]['submissions'] && $data[$index]['entrants']) ? round ((($data[$index]['submissions'] / $data[$index]['entrants']) * 100), 1) : '?');
			
			# Start an array to hold the questions
			$data[$index]['questions'] = array ();
			
			# Add the question texts to the array
			foreach ($questions as $question => $value) {
				if (substr ($question, 0, 1) != 'q') {continue;}	// Ignore fields which are not a question, e.g. ignore courseId but not q1howmany
				if (isSet ($course['excludeQuestions']) && in_array ($question, $course['excludeQuestions'])) {continue;}	// Ignore questions to exclude (e.g. due to subcourse for lecturers)
				if (isSet ($this->types[$group])) {
					$value = str_replace ('%type', $this->types[$group]['singular'], $value);
				}
				$data[$index]['questions'][$question]['text'] = $value;
			}
			
			# Get the available answers for multiple-choice ones
			$availableAnswers = array ();
			foreach ($data[$index]['questions'] as $question => $attributes) {
				if (array_key_exists ($question, $fields)) {
					$values = false;	// Use false rather than NULL as isSet(NULL) will result in this block being recomputed each time
					if (preg_match ('/enum\(\'(.*)\'\)/i', $fields[$question]['Type'], $matches)) {
						$values = explode ("','", $matches[1]);
						if (isSet ($values[0]) && (empty ($values[0]))) {
							unset ($values[0]);	// Remove '' <option>
						}
						if (strtoupper ($fields[$question]['Null']) == 'YES') {
							$values['[No response submitted]'] = '[No response submitted]';	// This is needed at this level to ensure consistency in the columns; the presentation formats may then discard it; note the key is specified so that it is easier to unset elsewhere if wanted
						}
					}
					$availableAnswers[$question] = $values;
				}
			}
			
			# Assign whether the question type is a multiple choice type (rather than free-form text type)
			foreach ($data[$index]['questions'] as $question => $attributes) {
				$data[$index]['questions'][$question]['isMultipleChoiceType'] = ($availableAnswers[$question] ? true : false);
			}
			
			# Compile the responses to each question in each submission
			foreach ($data[$index]['questions'] as $question => $attributes) {
				
				# For multiple choice type questions, set all the answers to 0
				$responses = array ();
				if ($data[$index]['questions'][$question]['isMultipleChoiceType']) {
					$responses = array_fill_keys ($availableAnswers[$question], 0);
				}
				
				# Add each submission
				if (isSet ($submissionsByCourseId[$course['id']])) {
					foreach ($submissionsByCourseId[$course['id']] as $submission) {
						if (array_key_exists ($question, $submission)) {	// array_key_exists used because response could be an empty string ''
							$response = $submission[$question];
							
							# Assign empty response as 'no response submitted'
							if ($data[$index]['questions'][$question]['isMultipleChoiceType']) {
								if ($response == '') {
									$response = '[No response submitted]';
								}
								
								# Add the response to the tally
								if (array_key_exists ($response, $responses)) {
									$responses[$response]++;
								}
								
							# Clean up free-form textual responses (i.e. ones where there are no 'availableAnswers', and skip empty responses
							} else {
								$response = str_replace ("\n\n", "\n", trim ($response));
								if (in_array (strtolower ($response), $emptyResponses)) {continue;}
								$responses[] = $response;
							}
						}
					}
				}
				
				# Add the responses to the master array
				$data[$index]['questions'][$question]['responses'] = $responses;
			}
		}
		
		# Save the results for this group
		return $data;
	}
	
	
	# Function to create a results page based on a supplied dataset of responses for a set of courses (tables version)
	private function resultsPageTabulated ($resultsByGroup, $includeTextualAnswers = false)
	{
		# Start the HTML
		$html  = "\n\n<h1>Course assessments for {$this->currentAcademicYear}</h1>";
		
		# Create each row
		foreach ($resultsByGroup as $group => $results) {
			$table = array ();
			foreach ($results as $course) {
				
				# Assign the key/heading (the course name)
				$key = $course['heading'];
				
				# Add in the number of respondents
				$table['Total responses'][$key] = $course['submissions'];
				
				# Add in the number of entrants
				$table['Total students taking the course'][$key] = $course['entrants'];
				
				# Add in the number of submissions
				$table['Response rate'][$key] = $course['responseRate'] . '%';
				
				# Add in the questions
				foreach ($course['questions'] as $question => $attributes) {
					if ($attributes['isMultipleChoiceType']) {
						foreach ($attributes['responses'] as $option => $instances) {
							$label = $attributes['text'] . ' - ' . $option;
							$table[$label][$key]  = $instances;
							if ($course['submissions']) {
								$percentage = (round (($instances / $course['submissions']) * 100));
								$table[$label][$key] .= " ({$percentage}%)";
							}
						}
					} else {
						$label = $attributes['text'];
						$table[$label][$key] = ($includeTextualAnswers ? application::htmlUl ($attributes['responses']) : '<span class="small comment">[Textual answers not shown in table summary]</span>');
					}
				}
			}
			
			# Show the table for this group
			$html .= "\n\n<h2 id=\"{$group}\">" . htmlspecialchars ($this->groupNameFormatted ($group)) . " assessments:</h2>";
			$html .= application::htmlTable ($table, true, 'assessments-export border lines compressed', true, false, $allowHtml = true, false, $addCellClasses = true, $addRowKeyClasses = true, array (), $compress = true);
		}
		
		# Show the HTML
		return $html;
	}
	
	
	# Function to create a results page based on a supplied dataset of responses for a set of courses (HTML version)
	private function resultsPageHtml ($resultsByGroup, $courseDetailsByGroup, $includePieCharts = true, $pieChartWidth, $pieChartHeight, $pieChartStub)
	{
		# Start the HTML
		$html  = '';
		
		# Start with text
		if ($this->userIsAdministrator) {
			$html .= "\n<p>You have access to the following results (or <a href=\"{$this->baseUrl}/export.html\" target=\"_blank\">export this data</a>):</p>";
		}
		
		# Add a button to hide numeric chart results, to enable textual answers to be selected (for copy-and-paste) easily
		$hidden = false;
		if ($this->userIsAdministrator) {
			$hidden = (isSet ($_POST['hidden']) && $_POST['hidden'] == '1');
			$html .= "\n" . '<form method="post" action="" class="right"><input type="hidden" name="hidden" value="' . ($hidden ? '0' : '1') . '" /><input type="submit" value="' . ($hidden ? 'Show all' : 'Hide multiple choice questions') . '" /></form>';
		}
		
		# Add the global droplist
		$html .= $this->globalDropList ($courseDetailsByGroup);
		
		# Loop through each course
		foreach ($resultsByGroup as $group => $results) {
			
			# Add the master heading and droplist
			$html .= "\n\n<h2 id=\"{$group}\">" . htmlspecialchars ($this->groupNameFormatted ($group)) . " assessments:</h2>";
			$html .= $this->dropList ($courseDetailsByGroup[$group]);
			
			# Add the data
			foreach ($results as $course) {
				
				# Start with the heading
				$html .= "\n<h3 id=\"{$course['href']}\">" . htmlspecialchars ($course['heading']) . '</h3>';
				if ($group == 'courses') {
					$html .= '<div class="warningbox"><p>Note: Details for courses apply to the whole course (potentially having several lecturers and supervisors), not a specific lecturer.</p></div>';
				}
				
				// If there is no data, return at this point
				if (!$course['submissions']) {
					$html .= "\n<p>No submissions have yet been made.</p>";
					continue;
				}
				
				# Show response rate data
				$rates = array ();
				$rates['Total responses'] = $course['submissions'];
				if ($course['submissions'] && $course['entrants']) {
					$rates['Total students taking the course'] = $course['entrants'];
					$rates['Response rate'] = '<strong>' . $course['responseRate'] . '%</strong>';
				}
				$html .= application::htmlTableKeyed ($rates, $keySubstitutions = array (), $omitEmpty = true, $class = 'lines', $allowHtml = true);
				$html .= '<br />';
				
				// Start a table of data
				$html .= "\n" . '<table class="lines regulated">';
				$html .= "\n\t<tr>";
				$html .= "\n\t\t<th>Question asked</th>";
				$html .= "\n\t\t" . '<th class="results">Results</th>';
				if ($includePieCharts) {$html .= "\n\t\t" . '<th class="piechart"></th>';}
				$html .= "\n\t</tr>";
				
				// Loop through each record and make the results into a table
				foreach ($course['questions'] as $question) {
					
					# If hiding, hide multiple choice types
					if ($hidden) {
						if ($question['isMultipleChoiceType']) {
							continue;
						}
					}
					
					// Show the title
					$html .= "\n\t<tr>";
					$html .= "\n\t\t<td class=\"question\">" . htmlspecialchars ($question['text']) . '</td>';
					
					// If the type is not a selection type, show it as a bullet-pointed list
					if (!$question['isMultipleChoiceType']) {
						$html .= "\n\t\t<td" . ($includePieCharts ? ' colspan="2"' : '') . '>';
						$html .= application::htmlUl ($question['responses'], 3, 'small compact', true, true, true);
						$html .= "\n\t\t</td>";
					} else {
						
						# Remove instances of zero 'no response submitted' responses
						if (isSet ($question['responses']['[No response submitted]']) && ($question['responses']['[No response submitted]'] == 0)) {
							unset ($question['responses']['[No response submitted]']);
						}
						
						// Start the column for the enclosing cell
						$html .= "\n\t\t<td>";
						
						// Put the results into a table
						$nesting = "\t\t\t";
						$html .= "\n$nesting" . '<table class="results border">';
						$html .= "\n$nesting\t<tr>";
						$html .= "\n$nesting\t\t<th>Option</th>";
						$html .= "\n$nesting\t\t<th>Number</th>";
						$html .= "\n$nesting\t\t<th>Percentage</th>";
						$html .= "\n$nesting\t</tr>";
						
						// Loop through each potential answer, adding the results and the percentage
						$percentages = array ();
						$mostPopular = max ($question['responses']);
						foreach ($question['responses'] as $option => $instances) {
							$html .= "\n$nesting\t\t<tr" . ($instances == $mostPopular ? ' class="highest"' : '') . '>';
							$html .= "\n$nesting\t\t\t<td>" . htmlspecialchars ($option) . ':</td>';
							$html .= "\n$nesting\t\t\t<td class=\"number\">" . $instances . ' / ' . $course['submissions'] . '</td>';
							$percentage = (round (($instances / $course['submissions']) * 100));
							$html .= "\n$nesting\t\t\t<td class=\"percentage\">" . $percentage . '%</td>';
							$html .= "\n$nesting\t</tr>";
							
							# Cache the percentages
							$optionCleaned = rawurlencode ($option);
							$percentages[$optionCleaned] = $percentage;
						}
						
						// End the table
						$html .= "\n{$nesting}</table>";
						
						# Close the second column
						$html .= "\n\t\t</td>";
						
						// Add in the pie chart for numeric types
						if ($includePieCharts) {$html .= "\n\t\t<td class=\"piechart\"><img width=\"{$pieChartWidth}\" height=\"{$pieChartHeight}\" src=\"{$pieChartStub}?values=" . implode (',', array_values ($percentages)) . '&amp;desc=' . implode (',', array_keys ($percentages)) . "&amp;width={$pieChartWidth}&amp;height={$pieChartHeight}\" alt=\"Pie chart of results\" /></td>";}
					}
					
					// End this result
					$html .= "\n\t</tr>";
				}
				
				// End this table of data
				$html .= "\n</table>";
			}
		}
		
		# Surround with a div
		$html = "\n\n<div id=\"assessmentsresults\">\n\n" . $html . "\n\n</div>\n\n";
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to provide a formatted version of the group name
	public function groupNameFormatted ($group)
	{
		# Strip any final 's' and capitalise
		return preg_replace ('/s$/', '', ucfirst ($group));
	}
	
	
	# Function to create a global drop-list
	private function globalDropList ($courseDetailsByGroup)
	{
		# Create the global droplist
		$globalList = array ();
		foreach ($courseDetailsByGroup as $group => $courses) {
			$globalList[$group]  = "<a href=\"#{$group}\">" . $this->groupNameFormatted ($group) . " assessments:</a>";
			$globalList[$group] .= ($group == 'courses' ? ' [Details apply to the entire course, not a specific lecturer]' : '');
			$globalList[$group] .= $this->dropList ($courses);
		}
		
		# Compile the HTML
		$html = application::htmlUl ($globalList, 0, 'small compact');
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to create a drop-list to each course's results
	private function dropList ($courses)
	{
		# Create a list of courses
		$list = array ();
		foreach ($courses as $index => $course) {
			$list[] = "<a href=\"#{$course['href']}\">{$course['heading']}</a>";
		}
		
		# Compile the HTML
		$html = application::htmlUl ($list, 1, 'small compact');
		
		# Return the list
		return $html;
	}
}

?>

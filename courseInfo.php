<?php //INCLUDE Scripts and $whoami value.
include($_SERVER['DOCUMENT_ROOT'] . '/staff/forms/formFunctions.php');
include($_SERVER['DOCUMENT_ROOT']. '/Connections/ML_DatabasesPDO.php');
$whoami = getenv('REMOTE_USER'); $my_nameis = ldapConnect($whoami);
include($_SERVER['DOCUMENT_ROOT'] .'/staff/header.php');
?>
<?php
//Debugging to see someone else's version.
//if($whoami == 'streatim'){$whoami = 'cspilker';}	

//Get Distinct Course Names
$courseQuery = "SELECT CourseID FROM ML_LRC.CourseInfo WHERE Librarian LIKE '".$whoami."';";
foreach($libraryDB->query($courseQuery) as $courseName){
	$courseList[] = $courseName['CourseID'];
}

if($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['courseID']) && in_array($_GET['courseID'], $courseList)){
	$courseID = $_GET['courseID'];
	$searchCourse = $libraryDB->prepare("SET @searchCourse = :searchCourse;");
	$searchCourse->bindValue(":searchCourse", $courseID, PDO::PARAM_STR);
	$searchCourse->execute();

	//OTHER Queries
	//AssessmentOther
	$fullQuery = "SELECT OtherField FROM ML_LRC.BridgeCourseAssessment WHERE CourseID = @searchCourse AND OtherField IS NOT NULL;";
	$assessQuery = $libraryDB->prepare($fullQuery);
	$assessQuery->execute();
	$assessOther = $assessQuery->fetchAll();

	//ProgramOther
	$fullQuery = "SELECT OtherField FROM ML_LRC.BridgeCourseProgram WHERE CourseID = @searchCourse AND OtherField IS NOT NULL;";
	$programQuery = $libraryDB->prepare($fullQuery);
	$programQuery->execute();
	$programOther = $programQuery->fetchAll();

	$updateQuery = "SELECT A.Name, A.Number, A.Section, A.Year, A.Semester, A.Students, A.Delivery, A.LibGuides, GROUP_CONCAT(DISTINCT B.LevelID) AS Levels, GROUP_CONCAT(DISTINCT C.AssessID) AS Assessments, GROUP_CONCAT(DISTINCT D.ProgramID) AS Programs, GROUP_CONCAT(DISTINCT E.ActivityID) AS Activities FROM ML_LRC.CourseInfo A LEFT JOIN ML_LRC.BridgeCourseLevel B ON A.CourseID = B.CourseID LEFT JOIN ML_LRC.BridgeCourseAssessment C ON A.CourseID = C.CourseID LEFT JOIN ML_LRC.BridgeCourseProgram D ON A.CourseID = D.CourseID LEFT JOIN ML_LRC.BridgeActivitiesCourses E ON A.CourseID = E.CourseID WHERE A.CourseID = @searchCourse GROUP BY A.CourseID";
	$courseInfQuery = $libraryDB->prepare($updateQuery);
	$courseInfQuery->execute();
	$courseInfoList = $courseInfQuery->fetchAll(); 
	$formValue = array(
		'Name' => $courseInfoList[0]['Name'],
		'Number' => $courseInfoList[0]['Number'],
		'Section' => $courseInfoList[0]['Section'],
		'Year' => $courseInfoList[0]['Year'],
		'Semester' => $courseInfoList[0]['Semester'],
		'Students' => $courseInfoList[0]['Students'],
		'Delivery' => $courseInfoList[0]['Delivery'],
		'Levels' => explode(',', $courseInfoList[0]['Levels']),
		'Assessments' => explode(',', $courseInfoList[0]['Assessments']),
		'Programs' => explode(',', $courseInfoList[0]['Programs']),
		'Activities' => explode(',', $courseInfoList[0]['Activities']),
		'LibGuides' => $courseInfoList[0]['LibGuides']
		);
}
?>
<?php 
if($_SERVER['REQUEST_METHOD'] == 'POST'){
	//Course Information Array.
	$courseInfo = array(
		$_POST['courseTitle'],
		$_POST['courseNumber'],
		$_POST['sectionNumber'],
		$_POST['year'],
		$_POST['semester'],
		$_POST['studentNum'],
		$_POST['deliveryMethod'],
		preg_replace( "/\r|\n/", "", $_POST['libGuideUse'] ),
		$_POST['librarian']
	);
	//Check to see if this is an update or a new one. If an update, update the CourseInfo and delete all bridge entries.
	
	if($_POST['update']==='FALSE'){
		//This is a new entry.
		//Insert Content Info. Needs to be done first because we need the CourseID.
		$contentInfoQuery = 'INSERT INTO ML_LRC.CourseInfo (Name, Number, Section, Year, Semester, Students, Delivery, LibGuides, Librarian) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?);';
		$submitQuery = $libraryDB->prepare($contentInfoQuery);
		$submitQuery->execute($courseInfo);
		$courseID = $libraryDB->lastInsertId();
	} else {
		$searchCourse = $libraryDB->prepare("SET @courseID = :courseID;");
		$searchCourse->bindValue(":courseID", $_POST['update'], PDO::PARAM_STR);
		$searchCourse->execute();
		$courseID = $_POST['update'];
		
		//Update ContentInfo, delete Bridges.
		$contentInfoQuery = 'UPDATE ML_LRC.CourseInfo A SET A.Name = ?, A.Number = ?, A.Section = ?, A.Year = ?, A.Semester = ?, A.Students = ?, A.Delivery = ?, A.LibGuides = ?, A.Librarian = ? WHERE A.CourseID = @courseID;';
		$submitQuery = $libraryDB->prepare($contentInfoQuery);
		$submitQuery->execute($courseInfo);

		//Delete Bridges for this Course
		$delActivity = "DELETE FROM ML_LRC.BridgeActivitiesCourses WHERE CourseID = @courseID";
		$submitQuery = $libraryDB->prepare($delActivity);
		$submitQuery->execute()	;

		$delLevel = "DELETE FROM ML_LRC.BridgeCourseLevel WHERE CourseID = @courseID";
		$submitQuery = $libraryDB->prepare($delLevel);
		$submitQuery->execute();

		$delAssess = "DELETE FROM ML_LRC.BridgeCourseAssessment WHERE CourseID = @courseID";
		$submitQuery = $libraryDB->prepare($delAssess);
		$submitQuery->execute();

		$delCP = "DELETE FROM ML_LRC.BridgeCourseProgram WHERE CourseID = @courseID";
		$submitQuery = $libraryDB->prepare($delCP);
		$submitQuery->execute();
	}

	//Assessment
	if(isset($_POST['assessments'])){
		foreach($_POST['assessments'] as $assessments){$assessValues[] = array($assessments, $courseID);}
	}
	//Course Values
	if(isset($_POST['courseLevel'])){
		foreach($_POST['courseLevel'] as $levelInput){
			$courseValues[] = array($courseID, $levelInput);
		}
	}

	//Program Values
	if(isset($_POST['schoolCollege'])){
		foreach($_POST['schoolCollege'] as $schoolCollege){
			$cpInsert[] = array($schoolCollege, $courseID);
		}
	}


	//Activity Values
	if(isset($_POST['activity'])){
		foreach($_POST['activity'] as $activityID){$activityInsert[] = array($activityID, $courseID);}
	}
	
	//Insert Assessments to the Bridge.
	if(isset($assessValues)){
		$bridgeAssessmentQuery = 'INSERT INTO ML_LRC.BridgeCourseAssessment (AssessID, CourseID) VALUES (?, ?);';
		$submitQuery = $libraryDB->prepare($bridgeAssessmentQuery);
		foreach($assessValues as $assessInsert){$submitQuery->execute($assessInsert);}
	}

	//Insert Course Level Values
	if(isset($courseValues)){
		$bridgeCourseQuery = 'INSERT INTO ML_LRC.BridgeCourseLevel (CourseID, LevelID) VALUES (?, ?);';
		$submitQuery = $libraryDB->prepare($bridgeCourseQuery);
		foreach($courseValues as $valueInsert){$submitQuery->execute($valueInsert);}
	}

	//Insert Bridge Course/Program values.
	if(isset($cpInsert)){
		$bridgeCPQuery = 'INSERT INTO ML_LRC.BridgeCourseProgram (ProgramID, CourseID) VALUES (?, ?);';
		$submitQuery = $libraryDB->prepare($bridgeCPQuery);
		foreach($cpInsert as $cpValue){$submitQuery->execute($cpValue);}
	}
	//Insert Activity Bridge values.
	if(isset($activityInsert)){
		$bridgeActivityQuery = 'INSERT INTO ML_LRC.BridgeActivitiesCourses (ActivityID, CourseID) VALUES (?, ?);';
		$submitQuery = $libraryDB->prepare($bridgeActivityQuery);
		foreach($activityInsert as $activityValues){$submitQuery->execute($activityValues);}
	}
}
?>
<?php

$otherShow = TRUE;

//Get a Distinct list of Dateperiods used in CourseInfo.
//An assumption made is that you only want to capture the upcoming academic year (if it starts this year) and the existing one...UNTIL two weeks after the start of the new Academic Year. Basically, at the start of the calendar year the upcoming academic year becomes available for selection, and the old academic year stops being accessible two weeks after the start of the new one.
//We are also going to get the Academic Years that are being counted here - those will be used to limit all the other lists.
$semesterYearQuery = [
	"SELECT DISTINCT A.Semester, A.Year, B.AcademicYear",
	"FROM ML_LRC.CourseInfo A",
	"LEFT JOIN ML_Public_Website.SemesterInfo B ON",
	"(A.Semester = B.Semester AND A.Year = YEAR(B.StartDate))",
	"WHERE A.Librarian LIKE '".$whoami."'",
	"AND (B.AcademicYear LIKE CONCAT((",
		"IF(CURRENT_DATE < DATE_ADD((",
			"SELECT A1.StartDate",
			"FROM ML_Public_Website.SemesterInfo A1",
			"WHERE A1.Semester = 'Summer I'",
			"AND YEAR(A1.StartDate) = YEAR(CURRENT_DATE)", 
			"LIMIT 1)",
		", INTERVAL 2 WEEK),", 
	"YEAR(DATE_SUB(CURRENT_DATE, INTERVAL 1 YEAR)), YEAR(CURRENT_DATE))), '%')",
	"OR B.AcademicYear LIKE CONCAT(YEAR(CURRENT_DATE), '%'))",
	"ORDER BY B.StartDate, B.EndDate"
];


foreach($libraryDB->query(implode(" ", $semesterYearQuery)) as $semesterInfo){
	$datePeriod[$semesterInfo['Year'].$semesterInfo['Semester']] = $semesterInfo['Year'] . ' - ' .$semesterInfo['Semester'];
	if(!in_array($semesterInfo['AcademicYear'], $academicArrays)){
		$academicArrays[] = $semesterInfo['AcademicYear'];
	}
}

if(count($academicArrays == 0)){
	$newAcademicQuery = [
		"SELECT DISTINCT A.AcademicYear",
		"FROM ML_Public_Website.SemesterInfo A",
		"WHERE A.AcademicYear LIKE CONCAT((",
			"IF(CURRENT_DATE < DATE_ADD((",
				"SELECT A1.StartDate",
				"FROM ML_Public_Website.SemesterInfo A1",
				"WHERE A1.Semester = 'Summer I'",
				"AND YEAR(A1.StartDate) = YEAR(CURRENT_DATE)", 
				"LIMIT 1)",
			", INTERVAL 2 WEEK),", 
		"YEAR(DATE_SUB(CURRENT_DATE, INTERVAL 1 YEAR)), YEAR(CURRENT_DATE))), '%')",
		"OR A.AcademicYear LIKE CONCAT(YEAR(CURRENT_DATE), '%')"
	];

	foreach($libraryDB->query(implode(" ", $newAcademicQuery)) as $acaYear){
		$academicArrays[] = $acaYear['AcademicYear'];
	}
}
//Make a list of academic years gathered in the above query.
$academicList = "'".implode("','", $academicArrays)."'";

//Get Distinct Course Names. Only use courses from the academic years listed above.
$courseQuery = [
	"SELECT A.CourseID, CONCAT(A.Name, IF(A.Section='', '', CONCAT(' - ', A.Section))) AS Name, CONCAT(A.Year, A.Semester) AS DatePeriod",
	"FROM ML_LRC.CourseInfo A",
	"LEFT JOIN ML_Public_Website.SemesterInfo B ON",
	"(A.Semester = B.Semester AND A.Year = YEAR(B.StartDate))",
	"WHERE A.Librarian LIKE '".$whoami."'",
	"AND B.AcademicYear IN (".$academicList.");"
];

foreach($libraryDB->query(implode(" ",$courseQuery)) as $courseName){
	$courseList[$courseName['CourseID']] = array('Name'=>$courseName['Name'], 'DatePeriod'=>$courseName['DatePeriod']);
}

//Get a Distinct List of years that have been put into ML_Public_Website.SemesterInfo
foreach($libraryDB->query("SELECT DISTINCT Year(StartDate) AS Year FROM ML_Public_Website.SemesterInfo WHERE AcademicYear IN (".$academicList.")") as $yearInfo){
	$yearList[] = $yearInfo['Year'];
}

//Select Course Level and IDs
foreach($libraryDB->query("SELECT * FROM ML_LRC.CourseLevel") as $courseLevel){$courseLevels[$courseLevel['LevelID']] = $courseLevel['Name'];}

//Select all Assessment Types.
foreach($libraryDB->query("SELECT * FROM ML_LRC.CourseAssessment WHERE AssessName NOT LIKE 'Other';") as $assessType){$assessTypes[$assessType['AssessID']] = $assessType['AssessName'];}

//Get a list of all Semesters
$semesterQuery = "SELECT DISTINCT Semester FROM ML_Public_Website.SemesterInfo ORDER BY Semester;";
foreach($libraryDB->query("SELECT DISTINCT Semester FROM ML_Public_Website.SemesterInfo ORDER BY Semester;") as $semesterName){
	$semesterList[] = $semesterName['Semester'];
}

//Get a list of all programs and colleges.
$programCount = 0;
foreach($libraryDB->query("SELECT A.ProgramID, A.Name, B.Name AS College FROM ML_Public_Website.Programs A LEFT JOIN ML_Public_Website.Colleges B ON A.CollegeID = B.ID ORDER BY A.Name;") AS $cp){
	$collegeProgram[$cp['College']][$cp['ProgramID']] = $cp['Name'];
	$programCount++;
}

$programDivCount = $programCount/3;
$programInfo = $collegeProgram['Campus Program'];
unset($collegeProgram['Campus Program']);
ksort($collegeProgram);
$collegeProgram['Campus Program'] = $programInfo;


//Get a list of relevant Activities
foreach($libraryDB->query("SELECT A.ActivityID, A.Name FROM ML_LRC.Activities A WHERE A.Countable = 0 ORDER BY A.Name;") as $activity){
	$activities[$activity['ActivityID']] = $activity['Name'];
}
?>
<div class="imageBanner toolsBanner">
	<div class="row large-12 columns">
		<div class="float-right">
			<h2>Course Information</h2>
		</div>
	</div>
</div>

<div class="row mainSection">	
	<div class="large-12 columns ">	<!--Navigation Section-->
		<nav aria-label="You are here:" role="navigation">
			<!--Breadcrumb-->
			<ul class="breadcrumbs">
				<li><a href="/staff"><i class="fa fa-home" aria-hidden="true"></i>&nbsp;Home</a></li>
				<li class="disabled">Tools</li>
				<li class="disabled">REC Tracking Form</li>
				<li>
					<span class="show-for-sr">Current: </span><a href="courseInteraction.php" target="_SELF">Countable Course Activities</a><br><strong>Course Information</strong>
				</li>
			</ul>
		</nav>
    </div>
	
<script type="text/javascript">
	function courseUpdate(){
		
		const selectList = document.getElementById('courseID');
		for (i=1; i<selectList.options.length; i++){selectList.options[i].hidden = true;}
		
		const dateClass = document.getElementById('datePeriod');
		const courses = document.getElementsByClassName(dateClass[dateClass.selectedIndex].value);
		for (i=0; i<courses.length; i++){courses[i].hidden = false;}
	}
</script>

	<!-- Main Menu Content -->
	<!--Drop down menu for Annual Reports or Individual Orders reports-->
	<h3>Data Input for <?php echo $my_nameis; ?></h3>
	<p><em><strong>Note:</strong> If there is an Assessment, Course/Program, or Course Support option you think should be added to the following form, please contact the head of the LRC.</em></p>
	<form name="courseUpdateForm" method="GET" target="_self">
		<div class="large-4 columns">
			<select id="datePeriod" onchange="courseUpdate()">
				<option value="Unselected">Select Year - Semester</option>
				<?php foreach($datePeriod as $key=>$semester){echo '<option value="'.$key.'">'.$semester.'</option>';} ?>
			</select>
			<!-- Second Column Information - List of Courses if a Course Information Form? --> 
		</div>
		<div class="large-4 columns">
			<select name="courseID" id="courseID">
				<option value="select">Select a Course</option>
				<?php foreach($courseList as $id=>$course){echo '<option name="courseOptions" class="'.$course['DatePeriod'].'" value="'.$id.'" hidden>'.$course['Name'].'</option>';} ?>
			</select>
		</div>
		<div class="large-4 columns">
			<input type="submit" value="Select Course">
		</div>		
	</form>
	<hr>
	
	<!--This is where the forms pop up. Hello, reports!-->
	<form name="courseInfoForm" method="POST" target="_self">
		<input type="hidden" id="librarian" name="librarian" value="<?php echo $whoami; ?>">
		<input type="hidden" id="update" name="update" value="<?php if(isset($formValue)){echo $courseID;}else{echo 'FALSE';} ?>">
		<div class="large-12 columns">		
			<div class="large-4 columns">
				<label for="courseTitle">Course/Program Title</label>
				<input type="text" id="courseTitle" name="courseTitle" <?php if(isset($formValue)){echo 'value="'.$formValue['Name'].'" ';} ?>required>
			</div>
			<div class="large-4 columns">
				<label for="courseNumber">Course Number</label>
				<input type="text" id="courseNumber" name="courseNumber" <?php if(isset($formValue)){echo 'value="'.$formValue['Number'].'" ';} ?>>
			</div>
			<div class="large-4 columns">
				<label for="sectionNumber">Section Number (if applicable)</label>
				<input type="text" id="sectionNumber" name="sectionNumber" <?php if(isset($formValue)){echo 'value="'.$formValue['Section'].'" ';} ?>>
			</div>
			<div class="large-4 columns">
				<label for ="year">Year</year>
				<select id="year" name="year">
					<?php 	foreach($yearList as $year){
								echo '<option id="'.$year.'" value="'.$year.'" name="'.$year.'"';
								if(in_array($year, $formValue)){echo 'selected';}
								echo '>'.$year.'</option>';} 
					?>
				</select>					
			</div>
			<div class="large-4 columns">
				<label for="semester">Semester</year>
				<select id="semester" name="semester">
					<?php
					foreach($semesterList as $semester){
						echo '<option id="'.$semester.'" name="'.$semester.'" ';
						if(in_array($semester, $formValue)){echo 'selected';}
						echo '>'.$semester.'</option>';
					}
					?>
				</select>
			</div>
			<div class="large-4 columns">
				<label for="studentNum"># of Students</label>
				<input type="number" id="studentNum" name="studentNum" <?php if(isset($formValue)){echo 'value="'.$formValue['Students'].'" ';} ?>>
			</div>
			<div class="large-4 columns">
			<label for="deliveryMethod">Delivery Method</label>
			<select id="deliveryMethod" name="deliveryMethod">
			<?php	$deliveryMethods = array('In-Person', 'Online', 'Hybrid');
					foreach($deliveryMethods as $method){
						echo '<option id="'.$method.'" name="'.$method.'" ';
						if($method == $formValue['Delivery']){echo 'selected';}
						echo '>'.$method.'</option>';
					}
			?>
				</select>			
			</div>
			<div class="large-4 columns">
				<label for="courseLevel">Course Level</label>
				<?php 	foreach($courseLevels as $id=>$level){
							echo '<div><input type="checkbox" value="'.$id.'" id="'.$level.'" name="courseLevel[]"';
							if(in_array($id, $formValue['Levels'])){echo 'checked';}					
							echo '><label for="'.$level.'">'.$level.'</label></div>';
						} 
				?>			
			</div>
			<div class="large-4 columns">
				<label for="assessmentMethod">Assessment(s) used in this course/program</label>
				<?php 	foreach($assessTypes as $key=>$assess){
							echo '<div><input type="checkbox" value="'.$key.'" id="'.$assess.'" name=assessments[] ';
							if(in_array($key, $formValue['Assessments'])){echo 'checked';}
							echo '><label for="'.$assess.'">'.$assess.'</label></div>';
						} 
				?>
			</div>	
		</div>
		<hr>
		<h4>College and Program Information</h4>
		<div class="larger-12 columns">					
			<!-- College/Program Information -->
			<div class="large-4 columns">
			<?php
			$i=0;
			foreach($collegeProgram as $college=>$programs){				
				if($i>($programDivCount-1)){echo '</div><div class="large-4 columns">'; $i = 0;}			
				//echo '<fieldset><legend><strong>'.$college.'</strong></legend><div>';
				echo '<strong>'.$college.'</strong>';
				foreach($programs as $id=>$program){	
					if($i>($programDivCount-1)){echo '</div><div class="large-4 columns">'; $i = 0;}										
					echo '<div><input type="checkbox" value="'.$id.'" id="'.$program.'" name="schoolCollege[]"';
					if(in_array($id, $formValue['Programs'])){echo ' checked';}
					echo '><label for="'.$program.'">'.$program.'</label></div>';
					$i++;
				}				
				//echo '</div></fieldset>';
			}
			?>
			</div>
		<hr>
		<h4>Faculty/Staff Collaboration: Course/Program Support</h4>
		<!-- Pull potential options from Database /write the "Other" script-->
		<div class="large-12 columns">
			<div class="large-6 columns">
			<?php
				$i=0;
				$activityDivCheck = (count($activities)/2)-1;
				foreach($activities as $key=>$activity){
					if($i>$activityDivCheck){echo '</div><div class="large-6 columns">'; $activityDivCheck = count($activities);}
					echo '<div><input type="checkbox" value="'.$key.'" id="'.$activity.'" name=activity[]"';
					if(in_array($key, $formValue['Activities'])){echo ' checked';}
					echo '><label for="'.$activity.'">'.$activity.'</label></div>';	
					$i++;
				}
			?>
			</div>
			&nbsp;<br>
			<div class="large-12 columns">
				<img src="guideID.PNG" width="300" height="216px" id="guideImage" style="border:solid;" alt="An image showing the location of the LibGuide ID in the guide list">
				<label for="libGuideUse">IDs for LibGuides Used (Please use the numerical LibGuide only. This is found in the far-left column of the guide list (under Content&#8594;Guides)).<br>
				If multiple LibGuides were used, separate IDs with a comma.</label>
				<input type="text" title="Please use the numerical LibGuide ID only, separated by a comma." pattern="^[0-9]*[,0-9]*$" value="<?php if(isset($formValue['LibGuides'])){echo $formValue['LibGuides'];} ?>" name="libGuideUse" id="libGuideUse">
			</div>
			<hr>
			<div class="large-12 columns">
				<div class="large-6 columns">			
					<input type="Submit" value="<?php if(isset($formValue)){echo 'Update Course Information';}else{echo 'Submit New Course';} ?>">
				</div>			
				<div class="large-6 columns">
					<?php
						if(isset($formValue)){echo '<a href="courseInfo.php" target="_SELF"><input type="button" value="Start New Course"></a>';}
					?>				
				</div>
			</div>
		</div>			
	</form>	
</div>
</div>
<?php include($_SERVER['DOCUMENT_ROOT'] .'/staff/footer.php');?> 
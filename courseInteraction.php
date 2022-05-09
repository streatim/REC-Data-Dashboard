<?php //INCLUDE Scripts and $whoami value.
include($_SERVER['DOCUMENT_ROOT'] . '/staff/forms/formFunctions.php');
include($_SERVER['DOCUMENT_ROOT']. '/Connections/ML_DatabasesPDO.php');
$whoami = getenv('REMOTE_USER'); $my_nameis = ldapConnect($whoami);
include($_SERVER['DOCUMENT_ROOT'] .'/staff/header.php');
?>
<?php
//Debugging to see someone else's version.
if($whoami == 'streatim'){$whoami = 'nfanders';}	

//Get a whitelist of Interactions by the librarian.
$interactQuery = "SELECT B.InteractionID AS ID FROM ML_LRC.CourseInfo A RIGHT JOIN ML_LRC.Interaction B ON A.CourseID = B.CourseID WHERE A.Librarian LIKE '".$whoami."';";
foreach($libraryDB->query($interactQuery) as $interaction){$WhiteListInteractions[] = $interaction['ID'];}

if($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['uinteractID']) && in_array($_GET['uinteractID'], $WhiteListInteractions)){
	$interactionID = $_GET['uinteractID'];
	$update = $interactionID;
	//Get information about the interaction (Date Period, Course, Type of Activity, Date of Activity, list of Activities)

	$searchInteract = $libraryDB->prepare("SET @interactID = :interactID;");
	$searchInteract->bindValue(":interactID", $interactionID, PDO::PARAM_STR);
	$searchInteract->execute();	
	
	$interactQuery = "SELECT A.InteractionID AS ID, A.CourseID, A.Type, A.InteractionDate AS DATE, CONCAT(B.Year, B.Semester) AS DatePeriod, GROUP_CONCAT(C.ActivityID) AS Activities FROM ML_LRC.Interaction A LEFT JOIN ML_LRC.CourseInfo B ON A.CourseID = B.CourseID RIGHT JOIN ML_LRC.BridgeActivitiesInteraction C ON A.InteractionID = C.InteractionID WHERE A.InteractionID = @interactID;";
	$courseInfQuery = $libraryDB->prepare($interactQuery);
	$courseInfQuery->execute();
	$courseInfoList = $courseInfQuery->fetchAll(); 

	$formValue = [
			'Semester' => $courseInfoList[0]['DatePeriod'],
			'CourseID'	 => $courseInfoList[0]['CourseID'],
			'ActivityDate'=> $courseInfoList[0]['DATE'],
			'ActivityType'=> $courseInfoList[0]['Type'],
			'Activities' => explode(',',$courseInfoList[0]['Activities'])
	];
}
?>
<?php //Process Form Insertions

if($_SERVER['REQUEST_METHOD'] === 'POST'){
	
	//$_POST['update'] = (if FALSE, it's a new record. If not false, it should be an interact ID.)

	//ML_LRC.Interaction
	$courseID = $_POST['realCourseList'];
	$interactionType = $_POST['interaction'];
	$interactionDate = $_POST['interactDate'];

	//ML_LRC.BridgeActivitiesInteraction
	foreach($_POST['activity'] as $activityList){
		$activityNumbersSubmit[] = $activityList;
	}

	$formInfo = [
		$interactionType,
		$courseID,
		$interactionDate
	];
	
	if($_POST['update'] === 'FALSE'){
		//Insert Interaction Info. Needs to be done first because we need the InteractionID.
		$interactionQuery = 'INSERT INTO ML_LRC.Interaction (Type, CourseID, InteractionDate) VALUES (?, ?, ?);';
		$submitQuery = $libraryDB->prepare($interactionQuery);		
		$submitQuery->execute($formInfo);
		$interactID = $libraryDB->lastInsertId();	
	} else {
		//We already have the ID - update ML_LRC.Interaction and delete the Bridge Entries.
		$interactID = $_POST['update'];

		$searchCourse = $libraryDB->prepare("SET @interactID = :interactID;");
		$searchCourse->bindValue(":interactID", $interactID, PDO::PARAM_STR);
		$searchCourse->execute();
		
		//Update Interaction, delete Bridges.
		$contentInfoQuery = 'UPDATE ML_LRC.Interaction A SET A.Type = ?, A.CourseID = ?, A.InteractionDate = ? WHERE A.InteractionID = @interactID;';
		$submitQuery = $libraryDB->prepare($contentInfoQuery);
		$submitQuery->execute($formInfo);

		//Delete Bridges for this Course
		$delActivity = "DELETE FROM ML_LRC.BridgeActivitiesInteraction WHERE InteractionID = @interactID";
		$submitQuery = $libraryDB->prepare($delActivity);
		$submitQuery->execute()	;	
	}

	//Set and Insert Bridge Information for Activity Values
	if(isset($_POST['activity'])){
		foreach($activityNumbersSubmit as $activityID){$activityInsert[] = array($interactID, $activityID);}
	}

	//Insert Activity Bridge values.
	if(isset($activityInsert)){
		$bridgeActivityQuery = 'INSERT INTO ML_LRC.BridgeActivitiesInteraction (InteractionID, ActivityID) VALUES (?, ?);';
		$submitQuery = $libraryDB->prepare($bridgeActivityQuery);
		foreach($activityInsert as $activityValues){$submitQuery->execute($activityValues);}
	}

}
?>
<?php //Build the form.
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
//Make a list of academic years gathered in the above query.
$academicList = "'".implode("','", $academicArrays)."'";

//Get Distinct Course Names for Librarian
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

//Get a list of Interactions by the librarian.
$interactQuery = [
	"SELECT CONCAT(DATE_FORMAT(B.InteractionDate, '%Y/%m/%d'), ' - ', C.Type) AS Name, B.InteractionID AS ID, B.CourseID AS CourseID",
	"FROM ML_LRC.CourseInfo A",
	"LEFT JOIN ML_Public_Website.SemesterInfo D ON",
	"(A.Semester = D.Semester AND A.Year = YEAR(D.StartDate))",
	"RIGHT JOIN ML_LRC.Interaction B ON A.CourseID = B.CourseID",
	"LEFT JOIN ML_LRC.InteractionType C ON B.Type = C.TypeID", 
	"WHERE A.Librarian LIKE '".$whoami."'",
	"AND D.AcademicYear IN (".$academicList.");"
];

foreach($libraryDB->query(implode(" ",$interactQuery)) as $interaction){
	$interactions[$interaction['ID']] = array('Name'=>$interaction['Name'], 'Course'=>$interaction['CourseID']);
}

//Get a list of potential interaction types.
foreach($libraryDB->query('SELECT * FROM ML_LRC.InteractionType;') as $intType){
	$intList[$intType['TypeID']] = $intType['Type'];
}
asort($intList);

//Get a list of activities.
foreach($libraryDB->query("SELECT A.ActivityID, A.Name FROM ML_LRC.Activities A WHERE A.Countable = 1 ORDER BY A.Name") as $activity){
	$activities[$activity['ActivityID']] = $activity['Name'];
}

$otherShow = FALSE;
?>
<div class="imageBanner toolsBanner">
	<div class="row large-12 columns">
		<div class="float-right">
			<h2>Countable Course Activities</h2>
		</div>
	</div>
</div>

<div class="row mainSection">	
	<div class="large-12 columns ">	<!--Navigation Section-->
		<nav aria-label="You are here:" role="navigation">
			<!--Breadcrumb-->
			<ul class="breadcrumbs">
				<li><a href="../../../staff"><i class="fa fa-home" aria-hidden="true"></i>&nbsp;Home</a></li>
				<li class="disabled">Tools</li>
				<li class="disabled">REC Tracking Form</li>
				<li>
					<span class="show-for-sr">Current: </span><strong>Countable Course Activities</strong><br><a href="courseInfo.php" target="_SELF">Course Information</a>
				</li>
			</ul>
		</nav>
    </div>

	<script type="text/javascript">
	const interactions = <?php echo json_encode($interactions); ?>;
	function updateList(redoList, checkList, update=''){
		const startPoint = (update!=='') ? 1 : 0;
		const selectList = document.getElementById(update+redoList);
		for (i=startPoint; i<selectList.options.length; i++){selectList.options[i].hidden = true;}
		
		const dateClass = document.getElementById(checkList);
		const courses = document.getElementsByClassName(update+dateClass[dateClass.selectedIndex].value);
		for (i=0; i<courses.length; i++){courses[i].hidden = false;}
	}
</script>

	<!-- Main Menu Content -->
	<!--Drop down menu for Annual Reports or Individual Orders reports-->
	<h3>Data Input for <?php echo $my_nameis; ?></h3>
	<p><em><strong>Note:</strong> If there is a Research Skill or Delivery Method option you think should be added to the following form, please contact the head of the LRC.</em></p>
	<form name="interactionUpdateForm" method="GET" target="_self">
		<div class="large-3 columns">
			<select id="datePeriod" onchange="updateList('courseList', 'datePeriod', 'u')">
			<option value="select">Select a Year - Semester</option>			
			<?php foreach($datePeriod as $key=>$semester){echo '<option value="'.$key.'">'.$semester.'</option>';} ?>
			</select>
		</div>
		<div class="large-3 columns">
			<select id="ucourseList" onchange="updateList('interactID', 'ucourseList', 'u')">
				<option value="select">Select a Course</option>
				<?php foreach($courseList as $id=>$course){echo '<option name="courseOptions" class="u'.$course['DatePeriod'].'" value="'.$id.'" hidden>'.$course['Name'].'</option>';} ?>
			</select>
			<!-- Second Column Information - List of Courses if a Course Information Form? --> 
		</div>
		<div class="large-3 columns">
			<select name="uinteractID" id="uinteractID">
				<option value="select">Select an Activity</option>
				<?php foreach($interactions as $id=>$interactionArray){echo '<option class="u'.$interactionArray['Course'].'" value="'.$id.'" hidden>'.$interactionArray['Name'].'</option>';} ?>				
			</select>
		</div>
		<div class="large-3 columns">
			<input type="submit" value="Update Form">
		</div>		
	</form>
	<hr>
	
	<!--This is where the forms pop up. Hello, reports!-->
	<form name="interactionForm" method="POST" target="_self">
		<input type="hidden" name="update" value="<?php if(isset($update)){echo $update;}else{echo 'FALSE';} ?>">
		<div class="large-12 columns">	
			<div class="large-3 columns">
			<label for="realDatePeriod">Select a Date Period</label>			
				<select name="realDatePeriod" id="realDatePeriod" onchange="updateList('realCourseList', 'realDatePeriod')">
				<?php foreach($datePeriod as $key=>$semester){echo '<option value="'.$key.'"';
				if($formValue['Semester']==$key){echo 'selected';}
				echo '>'.$semester.'</option>';} ?>
				</select>			
			</div>	
			<div class="large-3 columns">
			<label for="realCourseList">Select a Course</label>			
				<select name="realCourseList" id="realCourseList" required>
					<?php	foreach($courseList as $key=>$course){
						echo '<option value="'.$key.'" class="'.$course['DatePeriod'].'"';
						if($formValue['CourseID']==$key){echo ' selected';}else{echo ' hidden';}
						echo '>'.$course['Name'].'</option>';
					} 
					?>
				</select>
				<script type="text/javascript">updateList('realCourseList', 'realDatePeriod');</script>			
			</div>							
			<div class="large-3 columns">
			<label for="interaction">Activity Type</label>
				<select id="interaction" name="interaction">
				<?php 
				foreach($intList as $key=>$int){
					echo '<option value="'.$key.'" id="'.$int.'" name="'.$int.'"';
					if($key == $formValue['ActivityType']){echo ' selected';}
					echo '>'.$int.'</option>';} ?>
				</select>
			</div>
			<div class="large-3 columns">
				<label for="interactDate">Date of Activity</label>
				<input type="date" name="interactDate" id="interactDate" <?php if(isset($formValue['ActivityDate'])){echo 'value ="'.$formValue['ActivityDate'].'"';} ?>>
			</div>		
		</div>
		<h4>Research Skills Taught</h4>
		<div class="large-12 columns">
			<div class="large-6 columns">
			<?php
				$i = 0;
				$activityDivCount = (count($activities)/2)-1;
				foreach($activities as $key=>$activity){
					if($i>$activityDivCount){echo '</div><div class="large-6 columns">'; $activityDivCount = count($activities);}
					echo '<div><input type="checkbox" value="'.$key.'" id="'.$activity.'" name="activity[]"';
					if(in_array($key, $formValue['Activities'])){echo 'checked';}					
					echo '><label for="'.$activity.'">'.$activity.'</label></div>';
					$i++;
				}
			?>
			</div>
		</div>	
		<hr>
		<div class="large-12 columns">
			<div class="large-6 columns">
				<input type="Submit" value="<?php if(isset($formValue)){echo 'Update Course Activity';}else{echo 'Submit New Activity';} ?>">
			</div>
			<div class="large-6 columns">
				<?php
					if(isset($formValue)){echo '<a href="courseInteraction.php" target="_SELF"><input type="button" value="Start New Interaction"></a>';}
				?>
			</div>
		</div>		
	</form>	
</div>
<?php include($_SERVER['DOCUMENT_ROOT'] .'/staff/footer.php');?> 
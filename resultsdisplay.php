<?php include($_SERVER['DOCUMENT_ROOT'] . '/staff/forms/formFunctions.php'); ?>
<?php include($_SERVER['DOCUMENT_ROOT']. '/Connections/ML_DatabasesPDO.php'); ?>
<?php include($_SERVER['DOCUMENT_ROOT']. '/staff/header.php'); ?>
<?php $whoami = getenv('REMOTE_USER'); $my_nameis = ldapConnect($whoami); ?>
<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
<script type="text/javascript" src="results.js"></script>
<link rel="stylesheet" type="text/css" href="results.css">
<!--END HEADER-->
<body>
  <div class="imageBanner generalBanner">
    <div class="row large-12 columns">
      <div class="float-right">
        <h2>Research Education Data Dashboard</h2>
      </div>
    </div>
  </div>

<?php 
  //Run PHP queries for potential selection options.
  $librarianQuery = [
    "SELECT DISTINCT A.Librarian, CONCAT(IFNULL(B.StaffFName, B1.StaffFName), ' ', IFNULL(B.StaffLName, B1.StaffLName)) AS LibName", 
    "FROM ML_LRC.CourseInfo A",
    "LEFT JOIN ML_Public_Website.Staff B ON A.Librarian = B.UniqName",
    "LEFT JOIN ML_LRC.HistoricalUsers B1 ON A.Librarian = B1.UniqName",
    "ORDER BY IFNULL(B.StaffLName, B1.StaffLName) ASC"
  ];
  $librarians = $libraryDB->query(implode(" ", $librarianQuery))->fetchAll(PDO::FETCH_ASSOC);
  $selectSize = count($librarians);

  $semDateQuery = [
    "SELECT DISTINCT CONCAT(B.AcademicYear, ' - ', B.Semester) AS SemDate",
    "FROM ML_LRC.CourseInfo A",
    "LEFT JOIN ML_Public_Website.SemesterInfo B ON (A.Semester = B.Semester AND A.Year = YEAR(B.StartDate))",
    "ORDER BY B.StartDate, TRUE"
  ];
  $semesters = $libraryDB->query(implode(" ", $semDateQuery))->fetchAll(PDO::FETCH_ASSOC);

  $yearDateQuery = [
    "SELECT DISTINCT B.AcademicYear AS SemDate",
    "FROM ML_LRC.CourseInfo A",
    "LEFT JOIN ML_Public_Website.SemesterInfo B ON (A.Semester = B.Semester AND A.Year = YEAR(B.StartDate))",
    "ORDER BY B.StartDate, TRUE"
  ];

  $years = $libraryDB->query(implode(" ", $yearDateQuery))->fetchAll(PDO::FETCH_ASSOC);

  //Do a new query, gathering the year and semester combinations.
  $dateQuery = [
    "SELECT DISTINCT A.AcademicYear, A.Semester, A.StartDate, A.EndDate",
    "FROM ML_Public_Website.SemesterInfo A",
    "ORDER BY A.StartDate, TRUE, A.EndDate, TRUE"
  ];
  $dates = $libraryDB->query(implode(" ", $dateQuery))->fetchAll(PDO::FETCH_ASSOC);
  
  foreach($dates as $date){
    $formDates[$date['AcademicYear']][] = [
      'Semester' => $date['Semester'],
      'start' => $date['StartDate'],
      'end' => $date['EndDate']
    ];
  }

  //Do a query to gather College and Program information.
  $prgmQuery = [
    "SELECT DISTINCT A.ProgramID, A.Name, A.CollegeID",
    "FROM ML_Public_Website.Programs A",
    "ORDER BY A.CollegeID, TRUE, A.Name, TRUE"
  ];
  $programs = $libraryDB->query(implode(" ", $prgmQuery))->fetchAll(PDO::FETCH_ASSOC);
  foreach($programs as $program){
    $formPrograms[$program['CollegeID']][$program['ProgramID']] = $program['Name'];
  }

  //Set the Javascript JSON objects.
  echo '<script type="text/javascript">';
    echo 'const sortTypes = {';
      echo 'academicyear : '.json_encode($years).',';
      echo 'semester :'.json_encode($semesters);
    echo '};';

    echo 'const dateInfo = ';
      echo json_encode($formDates);
    echo ';';
  echo '</script>';

  //Function that sets the years in the Academic Year dropdowns.
  function setYear(){
    global $formDates;
    foreach($formDates as $year=>$array){
      echo '<option id="year'.$year.'" value="'.$year.'">'.$year.'</option>';
    }
  }

?>
  <!--Main Section-->
  <div class="row mainSection">
    <div class="large-12 columns ">
      <nav aria-label="You are here:" role="navigation">
      <!--Breadcrumb-->
        <ul class="breadcrumbs">
          <li><a href="../../../staff"><i class="fa fa-home" aria-hidden="true"></i>&nbsp;Home</a></li>
          <li class="disabled">Assessment</li>
          <li><span class="show-for-sr">Current: </span>Research Education Data Dashboard</li>
        </ul>
      </nav>
    </div>
	
    <div class="large-12 columns">  
      <form id="facetForm">
        <div id="facetFormFilters" class="mainSpace filters">
          <input type="hidden" name="uniq" id="uniq" value="<?php echo $whoami; ?>">
          <fieldset class="reportFieldsets">
            <legend>Date Period</legend>
              <div class="inner">
                <div>
                  <fieldset class="reportFieldsets">
                    <legend>Starting Year/Semester</legend>
                    <label for="startYear">Academic Year</label>
                    <select name="start" id="startYear" onchange="setSemester(this)">
                      <?php setYear(); ?>
                    </select>
                    <label for="startSemester">Semester</label>
                    <select name="startSemester" id="startSemester">
                    </select>
                  </fieldset>
                </div> 
                <div>  
                  <fieldset class="reportFieldsets">
                    <legend>Ending Year/Semester</legend>
                    <label for="endYear">Academic Year</label>
                    <select name="end" id="endYear" onchange="setSemester(this)">
                      <?php setYear(); ?>
                    </select>
                    <label for="endSemester">Semester</label>
                    <select name="endSemester" id="endSemester">
                    </select>
                  </fieldset>
                </div>
              </div>   
          </fieldset>
          <script type="text/javascript">
            document.getElementById('startYear').onchange();
            document.getElementById('endYear').onchange();
          </script>
          <fieldset class="reportFieldsets">
            <legend>Librarians</legend>
            <input type="radio" name="librarianList" id="true" value="true" onChange="librarianToggle()" checked>
              <label for="true">All Librarians</label>
            <input type="radio" name="librarianList" id="false" value="false" onChange="librarianToggle()">
              <label for="false">Some Librarians</label>
            <br>             
            <select id="librarians" name="librarians[]" class="formSelect" size=<?php echo $selectSize; ?> multiple disabled>
              <?php 
                foreach($librarians AS $librarian){
                  echo '<option id="'.$librarian['Librarian'].'" value="'.$librarian['Librarian'].'" selected>'.$librarian['LibName'].'</option>';
                }
              ?>
            </select>
          </fieldset>
          <fieldset class="reportFieldsets programQuestion">
          <legend>Programs</legend>
            <div class="programs">
              <div>
                <label for="allPrograms">Program List</label>
                <select name="allPrograms" id="allPrograms" multiple size="10">
                  <?php 
                    foreach($formPrograms as $college => $prgmArray){
                      echo '<optgroup label="'.$college.'">';
                      foreach($prgmArray as $programID => $programName){
                        echo '<option value="'.$programID.'">'.$programName.'</option>';
                      }
                      echo '</optgroup>';
                    }
                  ?>
                </select>
              </div>
              <div class="addDelete">
                <input type="button" value="Add All" onclick="programMove('allPrograms', 'selectedPrograms', 'all')">
                <br><br>
                <input type="button" value="Add Selected" onclick="programMove('allPrograms', 'selectedPrograms')">
                <br><br>
                <input type="button" value="Remove Selected" onclick="programMove('selectedPrograms', 'allPrograms')">
                <br><br>
                <input type="button" value="Remove All" onclick="programMove('selectedPrograms', 'allPrograms', 'all')">
              </div>
              <div>
                <label for="selectedPrograms">Selected Programs</label>
                <select name="selectedPrograms[]" id="selectedPrograms" multiple size="10">
                </select>
              </div>

              </div>
          </fieldset>    
        </div>
        <br>
        <input id="submitButton" type="button" value="Submit" onclick="passData()">
        <i class="fa-plus-circle fa-2x" id="expandButton" onclick="toggleButton()" hidden></i> 
      </form> 
      <hr>
      <div class="mainSpace">
        <div id="mainBox"></div>
        <div id="rightBox">
          <div id="tsvBox"><select id="tsvCheck" onchange="downloadTSV()"></select></div>
          <div id="tablesBox">
            <select id="tableCheck" onchange="drawTableBox()"></select>
            <table id="chartTable"></table>
          </div>
        </div>
      </div>
      <hr>
      <div class="mainSpace2">
        <div id="chartDivs"></div>    
      </div>
  </body>
  <script src="/staff/js/vendor/jquery.js"></script>
    <script src="/staff/js/vendor/what-input.js"></script>
    <script src="/staff/js/vendor/foundation.js"></script>
    <script src="/staff/js/app.js"></script>
  </footer>

<?php include($_SERVER['DOCUMENT_ROOT']. '/staff/footer.php'); ?>
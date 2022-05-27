<?php //STRUCTURE INFO
  /* --Notes from meeting talking about this --
  Notes from meeting:
  How effective are various stratagies for reaching out to faculty?
  Which research education skill/delivery method is used most?
  -- Is this delivery method used most commonly for delivering this skill?
  Which research skill is looked at most?
  Skills and Colleges


  From the original form:
  A list of all the courses that have librarians. (List)
  Breakdown of courses and semesters (how busy are each semester) (Pie Chart)

  Which College/Programs have courses librarians are involved in. (a series of charts)
  Chart of Research Skills Taught (series of charts)
  Course/Program Activities & Resources (series of charts) 
  Digital Education (series of charts)
  Chart of how many students?
  Chart of how many consultations were had.
  Usage data of Subject Guide (listed)
  Chart of which assessments were used. 
  
ACRL Specific Questions
- Attendance 




Total # of Students in Courses
*/
?>
<?php //Top Level Requirements (IF POST, Include Statements, Function declarations)
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        include($_SERVER['DOCUMENT_ROOT'] . '/staff/forms/formFunctions.php'); 
        include($_SERVER['DOCUMENT_ROOT'] . '/Connections/ML_DatabasesPDO.php');     
        require_once($_SERVER['DOCUMENT_ROOT'].'/secure/library/rec/secureQueries.php');

        function buildOutput($dataQuery, $divName, $chartType, $optionArray){
            global $output;
            $dataArray = buildData($dataQuery);
            if($dataArray !== NULL){
                $output[] = [
                    'Data' => $dataArray,
                    'ChartInfo' => [
                        'divName' => $divName,
                        'chartType' => $chartType,
                        'Options' => $optionArray,
                    ],
                ];
            }
        }

        function buildData($query){
            global $libraryDB;
            foreach($libraryDB->query(implode(" ", $query), PDO::FETCH_ASSOC) as $result){
                if(isset($result)){
                    if(!isset($outputData)){$outputData[] = array_keys($result);}
                    $outputData[] = array_values($result);
                }
            }
            return $outputData;
        }
?>
<?php //Put together the basic Query Elements (WHERE, FROM, etc.)
        //Gather all the Years and Semesters for the selected Date Period. 
        $semYearQuery = [
            "SELECT A.AcademicYear, A.Semester",
            "FROM ML_Public_Website.SemesterInfo A",
            "WHERE A.StartDate >= '".$_POST['startSemester']."' AND A.EndDate <= '".$_POST['endSemester']."'",
            "ORDER BY A.StartDate, TRUE, A.EndDate, TRUE"
          ];
          $semYears = $libraryDB->query(implode(" ", $semYearQuery))->fetchAll(PDO::FETCH_ASSOC);
    
        //Set the Years together with their Semesters
        foreach($semYears AS $dateSet){
            $years = explode("-", $dateSet['AcademicYear']);
            $yearKey = ($dateSet['Semester']) == 'Winter' ? $years[1] : $years[0];
            $dateReqs[$yearKey][] = $dateSet['Semester'];
        }

        //We'll also need to account for individual classes as necessary. 
        $whereLibClause = 'WHERE A.Librarian IN ("'.implode('","', $_POST['librarians']).'")';
        $wherePrgmClause = ' AND A1.ProgramID IN ("'.implode('","', $_POST['selectedPrograms']).'")';

        if(count($dateReqs)>0){$join = ' AND (';
            foreach($dateReqs AS $year=>$semesters){
                if(!isset($boolean)){$boolean = ' ';}else{$boolean = ' OR ';}
                $whereSemClause .= $boolean.'(A.Year = "'.$year.'" AND A.Semester IN ("'.implode('","', $semesters).'"))';
                $whereDateClause .= $boolean.'(Year(StartDate) = "'.$year.'" AND Semester IN ("'.implode('","', $semesters).'"))';
            }
            $whereSemClause .= ')';       
        }else{$join = ""; $whereSemClause = '';}
        $whereJointClause = $whereLibClause.$wherePrgmClause.$join.$whereSemClause;

        //Put together a date array for the monthly queries
        $dateQuery = [
            "(SELECT StartDate AS Date",
            "FROM ML_Public_Website.SemesterInfo",
            "Where ".$whereDateClause,
            "ORDER BY StartDate ASC LIMIT 1)",
            "UNION ALL",
            "(SELECT EndDate AS Date",
            "FROM ML_Public_Website.SemesterInfo",
            "Where ".$whereDateClause,
            "ORDER BY StartDate DESC LIMIT 1)"
        ];

        foreach($libraryDB->query(implode(" ",$dateQuery), PDO::FETCH_ASSOC) as $result){$dates[] = $result['Date'];}

        $start    = (new DateTime($dates[0]))->modify('first day of this month');
        $end      = (new DateTime($dates[1]))->modify('first day of next month');
        $interval = DateInterval::createFromDateString('1 month');
        $period   = new DatePeriod($start, $interval, $end);
      
        foreach ($period as $dt) {$dateArray[] = $dt->format("Y-n");}

        //Put together the required FROM clause to make sure the WHERE works.
        $fromClause  = 'FROM ML_LRC.CourseInfo A LEFT JOIN ML_LRC.BridgeCourseProgram A1 ON A.CourseID = A1.CourseID';
?>
<?php //Leadership-Only Embed Queries
    if(in_array($_POST['uniq'], $leadershipArray)){
        //Which Departments/Programs don't have a class with an Embedded Librarian in it?
        $graphArray[] = [
            "Query"=> [
                "SELECT DISTINCT A.Name, C.Name AS College",
                "FROM ML_Public_Website.Programs A LEFT JOIN ML_LRC.BridgeCourseProgram B ON A.ProgramID = B.ProgramID LEFT JOIN ML_Public_Website.Colleges C ON A.CollegeID = C.ID", 
                "WHERE B.CourseID IS NULL", 
                "ORDER BY C.Name DESC"
            ],
            "Options"=> [
                'title'=>'Departments/Programs without Librarians in Classes'
            ],    
            "Type" => "Download"
        ];

        
        //Which Departments/Programs don't have any interactions listed?
        $graphArray[] = [
            "Query"=> [
                "SELECT DISTINCT A.Name, C.Name AS College, COUNT(B.CourseID) AS Classes",
                "FROM ML_Public_Website.Programs A", 
                "RIGHT JOIN ML_LRC.BridgeCourseProgram B ON A.ProgramID = B.ProgramID",
                "LEFT JOIN ML_Public_Website.Colleges C ON A.CollegeID = C.ID",
                "LEFT JOIN ML_LRC.BridgeActivitiesCourses D ON B.CourseID = D.CourseID",
                "GROUP BY A.Name",
                "HAVING COUNT(D.CourseID) = 0"
            ],
            "Options"=> [
                'title'=>'Departments/Programs without Interactions'
            ],    
            "Type" => "Download"
        ];

        //Table describing how many classes a librarian is embedded in for the selected time period and programs.
        //2.) # of Courses broken down by Librarian (Table for the Selected Period)
        $graphArray[] = [
            "Query"=>   [
                "SELECT CONCAT(IFNULL(B.StaffFName, B1.StaffFName), ' ', IFNULL(B.StaffLName, B1.StaffLName)) AS 'Librarian Name',",
                "SUM(IF((".$whereSemClause.$wherePrgmClause.", 1, 0)) AS 'Courses'",
                "FROM ML_LRC.CourseInfo A",
                "LEFT JOIN ML_LRC.BridgeCourseProgram A1 ON A.CourseID = A1.CourseID",
                "LEFT OUTER JOIN ML_Public_Website.Staff B ON B.UniqName = A.Librarian",
                "LEFT OUTER JOIN ML_LRC.HistoricalUsers B1 ON A.Librarian = B1.UniqName",                
                "WHERE (B.DeptList LIKE '%Librarian%' OR B.DeptList IS NULL)",
                "GROUP BY B.UniqName",
                "ORDER BY COURSES DESC, IFNULL(B.StaffLName, B1.StaffLName) ASC"
            ],
            "Options"=> [
                'title'=> 'Total # of Courses a Librarian has been Embedded In'
            ],
            "Type" => 'TableChart'
        ];

    }
?>
<?php //BuildQueries.
//ACRL Questions 71/73 (Downloadable)
        //TypeIDs for the course types are: Synchronous (10), Asynchronous (11), In-Person(3). Each Row should be 
        $graphArray[] = [
            "Query"=> [
                "SELECT A.Name, A.Number, A.Section, A.Students AS 'Enrollment',",
                "COUNT(IF(B.Type = 3, 1, NULL)) AS 'In-Person',",
                "COUNT(IF(B.Type = 10, 1, NULL)) AS 'Online-Synchronous',",
                "COUNT(IF(B.Type = 11, 1, NULL)) AS 'Online-Asynchronous'",
                $fromClause." LEFT JOIN ML_LRC.Interaction B ON A.CourseID = B.CourseID",
                $whereJointClause,
                "GROUP BY A.Name",
            ],
            "Options"=> [
                'title'=>'ACRL Questions 71/73'
            ],    
            "Type" => "Download"
        ];


//Pie Chart : Total # of Courses, listed by Semester; if only one Semester, just say how many courses were there.
$graphArray[] = (count($_POST['semesterYear'])>1) ? [
    "Query"=>   [
        "SELECT CONCAT(A.Semester, ' - ', A.Year) AS 'Semester', COUNT(A.CourseID) AS Courses",
        $fromClause,
        $whereJointClause,
        "GROUP BY Semester"
    ],
    "Options"=> [
        'title' => 'Total # of Courses by Semester',
    ],
    "Type" => 'TableChart'
] : [
    "Query"=>   [
        "SELECT CONCAT(A.Semester, ' - ', A.Year) AS 'Semester', COUNT(A.CourseID) AS Courses",
        $fromClause,
        "LEFT JOIN ML_Public_Website.SemesterInfo B ON (A.Semester = B.Semester AND A.Year = YEAR(B.StartDate))",
        $whereJointClause,
        "GROUP BY CONCAT(A.Semester, ' - ', A.Year)",
        "ORDER BY B.StartDate, TRUE, B.EndDate, TRUE"
    ],
    "Options"=> [
        'title'=>'Total # of Courses Supported'
    ],
    "Type" => 'TableChart'
];

//Top 5 Activity Types Used (Total)
$graphArray[] = [
    "Query"=> [
        "SELECT COALESCE(C.Type, 'Total Activities'), COUNT(B.Type) AS 'Activity Type'",
        $fromClause." LEFT JOIN ML_LRC.Interaction B ON A.CourseID = B.CourseID LEFT JOIN ML_LRC.InteractionType C ON B.Type = C.TypeID",
        $whereJointClause,
        "AND C.Type IS NOT NULL",
        "GROUP BY C.Type",
        "WITH ROLLUP",
    ],
    "Options"=> [
        'title'=>'Activity Types Used (Total)'
    ],    
    "Type" => "Download"
];

//Raw Data for Debugging. Requires some custom variables (only returns that users information)
$libUniq = (in_array($_POST['uniq'], ['streatim', 'nfanders', 'cspilker'])) ? '%%' : $_POST['uniq'];
$username = ($libUniq == '%%') ? 'All Librarians' : $libUniq; 

$graphArray[] = [
    "Query"=> [
        "SELECT A.Name, A.Number, A.Section, A.Year, A.Semester, A.Students, A.Delivery, A.LibGuides, A.LibGuideUsage, A.Librarian,", 
        "IFNULL(GROUP_CONCAT(DISTINCT B.Name SEPARATOR ', '), 'None Listed') AS 'Faculty Support Activities Checked',",
        "(SELECT COUNT(F.CourseID) FROM ML_LRC.Interaction F WHERE F.CourseID = A.CourseID) AS 'Countable Courses Activities Counted',",
        "IFNULL(GROUP_CONCAT(DISTINCT C.AssessName SEPARATOR ', '), 'None Listed') AS 'Assessments',",
        "IFNULL(GROUP_CONCAT(DISTINCT D.Name SEPARATOR ', '), 'None Listed') AS 'Levels',",
        "IFNULL(GROUP_CONCAT(DISTINCT E.Name SEPARATOR ', '), 'None Listed') AS 'Program(s)'",
        "FROM ML_LRC.CourseInfo A",
        "LEFT JOIN ML_LRC.BridgeActivitiesCourses B1 ON A.CourseID = B1.CourseID",
        "LEFT JOIN ML_LRC.Activities B ON B1.ActivityID = B.ActivityID",
        "LEFT JOIN ML_LRC.BridgeCourseAssessment C1 ON A.CourseID = C1.CourseID",
        "LEFT JOIN ML_LRC.CourseAssessment C On C1.AssessID = C.AssessID",
        "LEFT JOIN ML_LRC.BridgeCourseLevel D1 ON A.CourseID = D1.CourseID",
        "LEFT JOIN ML_LRC.CourseLevel D ON D1.LevelID = D.LevelID",
        "LEFT JOIN ML_LRC.BridgeCourseProgram E1 ON A.CourseID = E1.CourseID",
        "LEFT JOIN ML_Public_Website.Programs E ON E1.ProgramID = E.ProgramID",
        "WHERE A.Librarian LIKE '".$libUniq."'",
        "GROUP BY A.CourseID",
    ],
    "Options"=> [
        'title'=>'Raw Data for '.$username
    ],    
    "Type" => "Download"
];

//# of Students Serviced
$graphArray[] = [
    "Query"=> [
        "SELECT SUM(Students) AS 'Total Number of Students Supported'",
        $fromClause,
        $whereJointClause
    ],
    "Options"=> [
        'title'=>'Total # of Students Supported'
    ],
    "Type" => "TableChart"
];

//# of LibGuide Views.
$graphArray[] = [
    "Query"=> [
        "SELECT SUM(LibGuideUsage) AS 'Total Number of LibGuide Views for Attached LibGuides'",
        $fromClause,
        $whereJointClause
    ],
    "Options"=> [
        'title'=>'Total # of LibGuide Views for Attached LibGuides'
    ],
    "Type" => "TableChart"
];

//- Assessment (Total for the period described)
$graphArray[] = [
    "Query"=>   [
        "SELECT C.AssessName as 'Assessment Type', COUNT(A.CourseID) AS 'Courses'",
        $fromClause.' RIGHT JOIN ML_LRC.BridgeCourseAssessment B ON A.CourseID = B.CourseID LEFT JOIN ML_LRC.CourseAssessment C ON B.AssessID = C.AssessID',
        $whereJointClause,
        "GROUP BY C.AssessName",
    ],
    "Options"=> [
        'title'=> 'Total # of Assessments Reported'
    ],
    "Type" => 'TableChart'
];

// - Course Level and Format, with X Axis being the Course Level, the Colors being the Format Types.
$graphArray[] = [
    "Query"=> [
        "SELECT D.Name AS CourseLevel, SUM(if(A.Delivery = 'In-Person', 1, 0)) AS 'In-Person', SUM(if(A.Delivery = 'Hybrid', 1, 0)) AS 'Hybrid', SUM(IF(A.Delivery = 'Online', 1, 0)) AS 'Online'",
        $fromClause." LEFT JOIN ML_LRC.BridgeCourseLevel C ON C.CourseID = A.CourseID LEFT JOIN ML_LRC.CourseLevel D ON C.LevelID = D.LevelID",
        $whereJointClause,

        "GROUP BY CourseLevel"
    ],
    "Options"=> [
        'title' => 'Total # of Courses by Delivery Method and Level',
        'isStacked' => 'true'
    ],
    "Type" => "BarChart"
];

//Total # of Courses by Level
$graphArray[] = [
    "Query"=> [
        "SELECT D.Name AS CourseLevel, COUNT(D.Name) AS 'Courses'",
        $fromClause." LEFT JOIN ML_LRC.BridgeCourseLevel C ON C.CourseID = A.CourseID LEFT JOIN ML_LRC.CourseLevel D ON C.LevelID = D.LevelID",
        $whereJointClause,
        "AND D.Name IS NOT NULL",
        "GROUP BY CourseLevel"
    ],
    "Options"=> [
        'title' => 'Total # of Courses by Level',
    ],
    "Type" => "TableChart"
];

//Total # of Courses by Delivery Method
$graphArray[] = [
    "Query"=> [
        "SELECT A.Delivery, COUNT(A.Delivery) AS 'Total'",
        $fromClause,
        $whereJointClause,
        "GROUP BY Delivery"
    ],
    "Options"=> [
        'title' => 'Total # of Courses by Delivery Method',
    ],
    "Type" => "TableChart"
];

//- Chart??? (Pie or Bar) : Total # of Courses broken down by College/Program (probably a Pie Chart)
$graphArray[] = [
    "Query"=> [
        "SELECT D.Name as College, COUNT(DISTINCT A.CourseID) AS Courses",
        $fromClause." LEFT JOIN ML_LRC.BridgeCourseProgram B ON A.CourseID = B.CourseID LEFT JOIN ML_Public_Website.Programs C ON B.ProgramID = C.ProgramID LEFT JOIN ML_Public_Website.Colleges D ON C.CollegeID = D.ID", 
        $whereJointClause,
        "AND D.Name IS NOT NULL",
        "GROUP BY D.Name"
    ],
    "Options"=> [
        'title' => 'Total # of Courses By College/Program',
        'is3D' => 'true',
        'pieSliceText' => 'value'
    ],
    "Type" => "PieChart"
];

/*
- Top 5:
   - Research Skills Covered (Broken Down by both TOTAL and by Month)
   - Types of Course Support Provided (Broken Down by both TOTAL and by Semester)
   - Activity Types Used (Broken Down by both TOTAL and by Month)
*/
//Top 5 Research Skills Covered (Total)
$graphArray[] = [
    "Query"=> [
        "SELECT D.Name, COUNT(C.ActivityID) AS Interactions",
        $fromClause." LEFT JOIN ML_LRC.Interaction B ON A.CourseID = B.CourseID LEFT JOIN ML_LRC.BridgeActivitiesInteraction C ON B.InteractionID = C.InteractionID LEFT JOIN ML_LRC.Activities D ON C.ActivityID = D.ActivityID",
        $whereJointClause,
        "GROUP BY D.Name",
        "ORDER BY Count(C.ActivityID) DESC",
        "LIMIT 5"
    ],
    "Options"=> [
        'title'=>'Top 5 Research Skills Covered (Total)'
    ],    
    "Type" => "TableChart"
];

//Top 5 Research Skills Covered (Downloadable)
$graphArray[] = [
    "Query"=> [
        "SELECT D.Name, COUNT(C.ActivityID) AS Interactions",
        $fromClause." LEFT JOIN ML_LRC.Interaction B ON A.CourseID = B.CourseID LEFT JOIN ML_LRC.BridgeActivitiesInteraction C ON B.InteractionID = C.InteractionID LEFT JOIN ML_LRC.Activities D ON C.ActivityID = D.ActivityID",
        $whereJointClause,
        "GROUP BY D.Name",
        "ORDER BY Count(C.ActivityID) DESC",
    ],
    "Options"=> [
        'title'=>'Research Skills Covered (Total)'
    ],    
    "Type" => "Download"
];

//Interaction Type by Course Program (downloadable) - Requested by Sophia
//This query requires an updated list of all the interaction types, so we're going to grab that super fast and loop together a coalesce/sum statement.
//Because of the current query, this may include duplicate information. A notice informs the user.
$coalesce = [];
foreach($libraryDB->query("SELECT A.Type FROM ML_LRC.InteractionType A") as $type){
    $coalesce[] = "coalesce(sum(case when B2.Type = '".$type['Type']."' then 1 end), 0) '".$type['Type']."'";
}

$graphArray[] = [
    "Query"=> [
        "SELECT C2.Name AS 'Program',",
        implode(", ", $coalesce),
        $fromClause,
        "RIGHT JOIN ML_LRC.Interaction B1 ON A.CourseID = B1.CourseID", 
        "LEFT JOIN ML_LRC.InteractionType B2 ON B1.Type = B2.TypeID", 
        "RIGHT JOIN ML_LRC.BridgeCourseProgram C1 ON A.CourseID = C1.CourseID",
        "LEFT JOIN ML_Public_Website.Programs C2 ON C1.ProgramID = C2.ProgramID",
        $whereJointClause,
        "GROUP BY C2.Name",
    ],
    "Options"=> [
        'title'=>'Total # of Interaction Types by Program',
        'notice'=>'Courses attached to multiple programs may result in duplicate interaction counts.'
    ],    
    "Type" => "Download"
];

//Top 5 Types of Course Support Provided (Total)
$graphArray[] = [
    "Query"=> [
        "SELECT C.Name, COUNT(B.ActivityID) AS 'Course Support'",
        $fromClause." LEFT JOIN ML_LRC.BridgeActivitiesCourses B ON A.CourseID = B.CourseID LEFT JOIN ML_LRC.Activities C ON B.ActivityID = C.ActivityID",
        $whereJointClause,
        "GROUP BY C.Name",
        "ORDER BY Count(B.ActivityID) DESC",
        "LIMIT 5"
    ],
    "Options"=> [
        'title'=>'Top 5 Types of Course Support Provided (Total)'
    ],    
    "Type" => "TableChart"
];

//Top Types of Course Support (downloadable)
$graphArray[] = [
    "Query"=> [
        "SELECT C.Name, COUNT(B.ActivityID) AS 'Course Support'",
        $fromClause." LEFT JOIN ML_LRC.BridgeActivitiesCourses B ON A.CourseID = B.CourseID LEFT JOIN ML_LRC.Activities C ON B.ActivityID = C.ActivityID",
        $whereJointClause,
        "GROUP BY C.Name",
        "ORDER BY Count(B.ActivityID) DESC",
    ],

    "Options"=> [
        'title'=>'Types of Course Support Provided (Total)'
    ],    
    "Type" => "Download"
];

//Top 5 Activity Types Used (Total)
$graphArray[] = [
    "Query"=> [
        "SELECT C.Type, COUNT(B.Type) AS 'Activity Type'",
        $fromClause." LEFT JOIN ML_LRC.Interaction B ON A.CourseID = B.CourseID LEFT JOIN ML_LRC.InteractionType C ON B.Type = C.TypeID",
        $whereJointClause,
        "GROUP BY C.Type",
        "ORDER BY Count(B.Type) DESC",
        "LIMIT 5"
    ],
    "Options"=> [
        'title'=>'Top 5 Activity Types Used (Total)'
    ],    
    "Type" => "TableChart"
];

//Top 5 Types of Course Support By Semester
$courseSupportSemSelect = "SELECT CONCAT(A.Semester, ' - ', A.Year) AS Semester";
$courseSupportSemSelectPrep = [
    "SELECT DISTINCT C.Name, C.ActivityID",
    $fromClause." LEFT JOIN ML_LRC.BridgeActivitiesCourses B ON A.CourseID = B.CourseID LEFT JOIN ML_LRC.Activities C ON B.ActivityID = C.ActivityID",
    $whereJointClause,
    "GROUP BY C.Name",
    "ORDER BY Count(B.ActivityID) DESC",
    "LIMIT 5"
];

foreach($libraryDB->query(implode(" ", $courseSupportSemSelectPrep), PDO::FETCH_ASSOC) as $result){
    $courseSupportSemSelect .= ", SUM(if(B.ActivityID = '".$result['ActivityID']."', 1, 0)) AS '".$result['Name']."'";
}

$graphArray[] = [
    "Query"=> [
        $courseSupportSemSelect,
        $fromClause." LEFT JOIN ML_LRC.BridgeActivitiesCourses B ON A.CourseID = B.CourseID",
        $whereJointClause,
        "GROUP BY Semester"
    ],
    "Options"=> [
        'title' => 'Top 5 Course Support Options for the Selected Date Period',  
        'seriesType' => 'bars',
    ],
    "Type" => "ComboChart"
];


if(count($dateArray)<13){
    //Top 5 Activity Types Used (by Month)
    $activityTypePrep = [
        "SELECT C.Type, C.TypeID",
        $fromClause." LEFT JOIN ML_LRC.Interaction B ON A.CourseID = B.CourseID LEFT JOIN ML_LRC.InteractionType C ON B.Type = C.TypeID",
        $whereJointClause,
        "GROUP BY C.Type",
        "ORDER BY Count(B.Type) DESC",
        "LIMIT 5"
    ];
    foreach($libraryDB->query(implode(" ", $activityTypePrep), PDO::FETCH_ASSOC) as $result){
        $activityTypeMonSelect .= ", IFNULL(SUM(if(B.Type = '".$result['TypeID']."', 1, 0)), 0) AS '".$result['Type']."'";
    }
    $monthArray = array();
    foreach($dateArray as $date){
        $queryArray = [
            "SELECT '".$date."' ".$activityTypeMonSelect,
            $fromClause." LEFT JOIN ML_LRC.Interaction B ON A.CourseID = B.CourseID",
            "WHERE CONCAT(YEAR(B.InteractionDate), '-', MONTH(B.InteractionDate)) = '".$date."'"
        ];
        if(!isset($union)){$union = '';}else{$union = 'UNION ALL';}
        $monthArray[] = $union;
        $monthArray[] = implode(" ", $queryArray);
    }
    $graphArray[] = [
        "Query" => $monthArray,
        "Options" => [
            'title' => 'Top 5 Interaction Types by Month',
            'seriesType' => 'bars',
        ],
        "Type" => "ComboChart"
    ];

    //Top 5 Research Skills (by Month)
    $researchSkillPrep = [
        "SELECT D.Name, C.ActivityID",
        $fromClause." LEFT JOIN ML_LRC.Interaction B ON A.CourseID = B.CourseID LEFT JOIN ML_LRC.BridgeActivitiesInteraction C ON B.InteractionID = C.InteractionID LEFT JOIN ML_LRC.Activities D ON C.ActivityID = D.ActivityID",
        $whereJointClause,
        "GROUP BY D.Name",
        "ORDER BY Count(C.ActivityID) DESC",
        "LIMIT 5"
    ];
    foreach($libraryDB->query(implode(" ", $researchSkillPrep), PDO::FETCH_ASSOC) as $result){
        $researchSkillMonSelect .= ", IFNULL(SUM(if(C.ActivityID = '".$result['ActivityID']."', 1, 0)), 0) AS '".$result['Name']."'";
    }

    $monthArray = array();
    foreach($dateArray as $date){
        $queryArray = [
            "SELECT '".$date."' ".$researchSkillMonSelect,
            $fromClause." LEFT JOIN ML_LRC.Interaction B ON A.CourseID = B.CourseID LEFT JOIN ML_LRC.BridgeActivitiesInteraction C ON B.InteractionID = C.InteractionID",
            "WHERE CONCAT(YEAR(B.InteractionDate), '-', MONTH(B.InteractionDate)) = '".$date."'"
        ];
        if(!isset($union2)){$union2 = '';}else{$union2 = 'UNION ALL';}
        $monthArray[] = $union2;
        $monthArray[] = implode(" ", $queryArray);
    }


    $graphArray[] = [
        "Query" => $monthArray,
        "Options" => [
            'title' => 'Top 5 Research Skills by Month',
            'seriesType' => 'bars',
        ],
        "Type" => "ComboChart"
    ];
}

    foreach($graphArray as $graph){
        if(isset($graph['Options'])){$options = $graph['Options'];}else{$options = array();}
        $options['backgroundColor'] = '#F9F9F9';
        buildOutput($graph['Query'], 'Div', $graph['Type'], $options);
    }
?>
<?php //Echo Output and end the IF Statement.
        echo json_encode($output, JSON_NUMERIC_CHECK);
    }
?>
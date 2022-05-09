google.charts.load("current", {packages:["corechart"]});
google.charts.load('current', {packages: ['table']});
let chartDataObject = new Object();

function moveProgram(source, target, isAll = false){
    let addList = programBuildNewList(source, isAll);
    programCreateList(addList, target);
}

function programBuildNewList(source, isAll){
    const selectedList = document.getElementById(source);
    //Put together the list of items to move.
    const moveList = (isAll) ? selectedList.querySelectorAll('option') : selectedList.querySelectorAll('option:checked');

    //Go through and get the information of each selected item, then remove it from the program list.
    let addList = new Array();
    for(i=0; i<moveList.length; i++){
        let parent = moveList[i].parentElement;
        let parentGroup = parent.label;
        addList.push({
            'Parent' : parentGroup,
            'Value' : moveList[i].value,
            'Text' : moveList[i].innerText
        });
        //Remove from the Add List.
        selectedList.remove(moveList[i].index);
        if(parent.childElementCount === 0){
            //There are no children left under this parent. Go ahead and remove the optgroup.
            parent.remove();
        }
    }
    //Sort the addList by Parent, then each parent group by the name. At present this is going to be done twice (because, in theory, when I get a better workflow going here we'll only need to sort here)
    addList.sort((a, b) => a.Parent.localeCompare(b.Parent) || a.Text.localeCompare(b.Text));
    return addList;
}

function programCreateList(addList, target){
    //This isn't the most efficient way to do this, I believe, but basically we'll copy the target box, clear it, and then merge everything and rewrite it. It's...not great. But it'll get it done for right now.
    //Copy the target box.
    let newList = document.getElementById(target);
    let copyList = addList;
    let newOptions = newList.querySelectorAll('option');
    if(newOptions.length>0){
        //There are existing options. We'll need to copy it out.
        for(i=0; i<newOptions.length; i++){
            let parent = newOptions[i].parentElement;
            let parentGroup = parent.label;
            copyList.push({
                'Parent' : parentGroup,
                'Value' : newOptions[i].value,
                'Text' : newOptions[i].innerText
            });
        }
    }
    //Now sort the copyList array. This is a repeated statement - if there was nothing copied, we essentially sort the same object the same way twice. Once we fix up this workflow, we should be able to do this only once.
    copyList.sort((a, b) => a.Parent.localeCompare(b.Parent) || a.Text.localeCompare(b.Text));

    //Delete the old list.
    newList.innerHTML = '';

    //Now run through the Copy List and add each object.
    for(i=0; i<addList.length; i++){
        let parent = addList[i].Parent;
        let value = addList[i].Value;
        let text = addList[i].Text;

        if(newList.querySelectorAll('optgroup[label="'+parent+'"]').length === 0){
            //The optGroup doesn't exist.
            let parentGroup = document.createElement('optGroup');
            parentGroup.label = parent;
            newList.appendChild(parentGroup);
        } 

        let parentElement = newList.querySelectorAll('optGroup[label="'+parent+'"]')[0];
        let option = document.createElement('option');
            option.value = value;
            option.innerText = text;
            parentElement.appendChild(option);
    }
}


function chartType(divElement, data, options, type) {
    switch(type){
        case 'AnnotationChart':{
            let chart = new google.visualization.AnnotationChart(divElement);
            chart.draw(data, options);
            break;
        }     
        case 'AreaChart':{
            let chart = new google.visualization.AreaChart(divElement);
            chart.draw(data, options);
            break;
        }   
        case 'BarChart':{
            let chart = new google.visualization.ColumnChart(divElement);
            chart.draw(data, options);
            break;
        }
        case 'BubbleChart':{
            let chart = new google.visualization.BubbleChart(divElement);
            chart.draw(data, options);
            break;
        }
        case 'CalendarChart':{
            let chart = new google.visualization.Calendar(divElement);
            chart.draw(data, options);
            break;
        }
        case 'CandlestickChart':{
            let chart = new google.visualization.CandlestickChart(divElement);
            chart.draw(data, options);
            break;
        }
        case 'ColumnChart':{
            let chart = new google.visualization.DataView(divElement);
            chart.draw(data, options);
            break;
        }
        case 'ComboChart':{
            let chart = new google.visualization.ComboChart(divElement);
            chart.draw(data, options);
            break;
        }
        case 'GanttChart':{
            let chart = new google.visualization.Gantt(divElement);
            chart.draw(data, options);
            break;
        }
        case 'Gauge':{
            let chart = new google.visualization.Gauge(divElement);
            chart.draw(data, options);
            break;
        }
        case 'Histogram':{
            let chart = new google.visualization.Histogram(divElement);
            chart.draw(data, options);
            break;
        }
        case 'LineChart':{
            let chart = new google.visualization.LineChart(divElement);
            chart.draw(data, options);
            break;
        }
        case 'OrgChart':{
            let chart = new google.visualization.OrgChart(divElement);
            chart.draw(data, options);
            break;
        }
        case 'PieChart':{
            let chart = new google.visualization.PieChart(divElement);
            chart.draw(data, options);
            break;
        }
        case 'SankeyChart':{
            let chart = new google.visualization.Sankey(divElement);
            chart.draw(data, options);
            break;
        }
        case 'ScatterChart':{
            let chart = new google.visualization.ScatterChart(divElement);
            chart.draw(data, options);
            break;
        }
        case 'SteppedAreaChart':{
            let chart = new google.visualization.SteppedAreaChart(divElement);
            chart.draw(data, options);
            break;
        }
        case 'TableChart':{
            let chart = new google.visualization.Table(divElement);
            chart.draw(data, options);
            break;
        }
        case 'TreeMapChart':{
            let chart = new google.visualization.TreeMap(divElement);
            chart.draw(data, options);
            break;
        }
        case 'WordTree':{
            let chart = new google.visualization.WordTree(divElement);
            chart.draw(data, options);
            break;
        }
    }    
}

function clearHTML(id){
    if(document.getElementById(id)){
        document.getElementById(id).innerHTML = '';
    }
}

function csvOptionsCreate(array, chartID){
    let option = document.createElement("option");
    option.id = (chartID) ? chartID : 'None';
    if(chartID === 0){option.id = 0;}
    option.innerText = (array) ? array.ChartInfo.Options.title : 'Select a Report to Download';
    document.getElementById('tsvCheck').appendChild(option);
}

function dateValidate(){
    const start = document.getElementById('startSemester');
    const startDate = new Date(start.value);
    const end = document.getElementById('endSemester');
    const endDate = new Date(end.value);
    return startDate<=endDate;
}

function downloadTSV(){
    let optionTSV = document.getElementById('tsvCheck');
    let chartID = optionTSV.options[optionTSV.selectedIndex].id;
    if(chartID == "None"){return;}    
    let confirm = window.confirm("Do you want to Download this Report?\n"+chartDataObject[chartID].ChartInfo.Options.title);
    if(confirm === true){
        let tsv='';
        //Check to see if the .tsv includes a notice and, if so, make it the first line.
        if(chartDataObject[chartID].ChartInfo.Options.notice){
            tsv += '"'+chartDataObject[chartID].ChartInfo.Options.notice+"\"\n";
        }
        chartDataObject[chartID].Data.forEach(function(row){
            tsv += '"';
            tsv += row.join('"\t"');
            tsv += "\"\n";
        });
    
        //Create a fake link and then "click" it, downloading the file.
        //Create the Link to the Files.
        let hiddenTSV = document.createElement('a');
        hiddenTSV.href = 'data:text/tsv;charset=utf-8,' + encodeURI(tsv);
        hiddenTSV.target = '_blank';
        hiddenTSV.download = chartDataObject[chartID].ChartInfo.Options.title+'.tsv';
        document.getElementById('tsvCheck').appendChild(hiddenTSV);
        hiddenTSV.click();    
    }
    document.getElementById('tsvCheck').selectedIndex = 0;
}

function drawChart(array, divNum){
    const type = array.ChartInfo.chartType;
    if(type=="TableChart"){drawTableOptions(array, divNum);}
    else if(type=="Download"){
        if(document.getElementById('tsvCheck').innerHTML == ''){csvOptionsCreate();}
        csvOptionsCreate(array, divNum);
    }else{
        const data = google.visualization.arrayToDataTable(array.Data); 
        const options = array.ChartInfo.Options;
        const divID = "chart"+divNum;
        if(firstPreviewBox == ''){firstPreviewBox = divID;}
        let actDiv = document.createElement("div"); // Create a <div> element
        actDiv.classList.add('resultGraph');
        actDiv.onclick = function(){drawMainBox(divID);};
        actDiv.id = divID;
        document.getElementById("chartDivs").appendChild(actDiv);
        const divElement = document.getElementById(divID);
        chartType(divElement, data, options, type);
    }
}

function drawMainBox(chartID){
    let selectedList = document.getElementsByClassName('selected');
    for(i=0;i<selectedList.length;i++){selectedList[i].classList.remove('selected');}
    document.getElementById(chartID).classList.add('selected');
    const array = chartDataObject[chartID.replace('chart', '')];
    const data = google.visualization.arrayToDataTable(array.Data); 
    const options = array.ChartInfo.Options;
    const type = array.ChartInfo.chartType;
    const divElement = document.getElementById('mainBox');
    chartType(divElement, data, options, type);    
}

function drawTableBox(){
    let optionTable = document.getElementById('tableCheck');
    chartID = optionTable.options[optionTable.selectedIndex].id;
    let table = document.getElementById('chartTable');
    table.innerHTML = '';

    //Create Rows
    for(i=1;i<chartDataObject[chartID].Data.length;i++){
        let row = table.insertRow();
        for(p=0;p<chartDataObject[chartID].Data[i].length;p++){
            let cell = row.insertCell();
            cell.innerHTML = chartDataObject[chartID].Data[i][p];
        }
    }

    //Create the Headers for the Table
    let header = table.createTHead();
    let headerRow = header.insertRow();
    for(i=0;i<chartDataObject[chartID].Data[0].length;i++){
        let cell = headerRow.insertCell();
        cell.innerHTML = "<strong>"+chartDataObject[chartID].Data[0][i]+"</strong>";
    }
}

function drawTableOptions(array, chartID){
    let option = document.createElement("option");
    option.id = chartID;
    option.innerText = array.ChartInfo.Options.title;
    document.getElementById('tableCheck').appendChild(option);
}

function librarianToggle(){
    const libList = document.getElementById('librarians');
    libList.disabled = !libList.disabled;
    for(i=0;i<libList.children.length;i++){
        libList.children[i].selected = libList.disabled;
    }
}

function passData() {
    //First thing we do is validate that the dates are set appropriately (start date before end date)
    if(!dateValidate()){
        window.alert('The end date must be equal to or after the selected start date.');
        return false;
    }
    let debug=false;

    if(document.getElementsByClassName('mainBox').length==0){
        document.getElementById('mainBox').classList.add('mainBox');
    }
    //Check if the librarian's list is disabled- we need to tempoararily reenable it.
    const libList = document.getElementById('librarians');
    let reenable = false;
    if(libList.disabled == true){libList.disabled = false; reenable = true;}
    if(!debug){
        $.ajax({
            type: 'post',
            url: "resultQuery.php",
            dataType: "json",
            data: $('form').serialize(),
            success: function (response) {
                document.getElementById('mainBox').style.visiblity = "visible";
                document.getElementById('mainBox').innerHTML = response;
                
                chartDataObject = response; 
                clearHTML('chartDivs');
                clearHTML('tableCheck');
                clearHTML('tsvCheck');
                document.getElementById('mainBox').style.visibility = "visible";
                document.getElementById('rightBox').style.visibility = "visible";
                firstPreviewBox = '';
                for(i=0;i<response.length;i++){
                    drawChart(chartDataObject[i], i);
                }
                drawMainBox(firstPreviewBox);
                drawTableBox();
            },
            error: function(e){
                //console.log("The request failed");
                console.log(e.statusText);
            }  
        });
    } else {
        $.ajax({
            type: 'post',
            url: "resultQuery.php",
            dataType: "html",
            data: $('form').serialize(),
            success: function (response) {
                console.log(response);
            },
            error: function(e){
                //console.log("The request failed");
                console.log(e.statusText);
            }  
        });
    }	 
    //Re-enable LibList if Necessary.
    if(reenable){libList.disabled = true;}
}

function pickTime(){
    clearHTML('dates');
    //Get whichever value is selected.
    let dateList = document.getElementsByName('reportBy');
    let selected = '';
    for(i=0;i<dateList.length;i++){
        if(dateList[i].checked == true){
            selected = dateList[i].id;
        }
    }
    const newList = sortTypes[selected];
    for(i=0;i<newList.length;i++){
        let option = document.createElement('option');
            option.id = newList[i].SemDate;
            option.value = newList[i].SemDate;
            option.innerHTML = newList[i].SemDate;
            document.getElementById('dates').appendChild(option);
    }
}

function setSemester(selectObject){
    //Set variables.
    const startEnd = selectObject.name;
    const semesterID = startEnd+'Semester';
    const year = selectObject.value;

    //Sets the Semester based on the academic year provided.
    const semesterList = document.getElementById(semesterID);

    //Clear the original list.
    clearHTML(semesterID);

    for(i=0;i<dateInfo[year].length;i++){
        let semesterArray = dateInfo[year][i];
        let semesterOption = document.createElement('option');
            semesterOption.id = startEnd+semesterArray.Semester;
            semesterOption.value = semesterArray[startEnd];
            semesterOption.innerText = semesterArray.Semester;
            semesterList.appendChild(semesterOption);
    }
}







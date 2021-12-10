//TODO: Add ability to add notes

document.addEventListener("DOMContentLoaded", function(){

// these need to be changed to arrays pulled from database
    var arrayOfItems = [['Objective 1','Inject','1'], ['Objective 2','Inject','2'], ['Learn about xxx 2.23,2.24', 'Artefact', '3'], ['Learn about new x2.23','Artefact', '4']];
    var arrayOfRequests = [['Can you x?','x', '5'], ['Can you y?','y','6'], ['Can you z','z','7'], ['Can we get baseline?', 'baseline','8']];
    var arrayOfIntereactions = [['Conversation with 2.22','conv w/2.22 desc','9'], ['Students Understand', 'Students Understand desc','10'], ['Conversation with 2.20','Desc 2.20','11']];

    var rightcard = false;
    var tempblock;
    var tempblock2;

    // Define item html block as empty
    var itemsSidePanelHtmlString = '';

    // Define requests html block as empty
    var requestsSidePanelHtmlString = '';

    // Define requests html block as empty
    var interactionsSidePanelHtmlString = '';


    var dragElementsHtmlString = [''];


    //cards on sidenav
    arrayOfItems.forEach (
        element => itemsSidePanelHtmlString += '<div class="blockelem create-flowy noselect" id="' + element[2] + '" ><input type="hidden" name="blockelemtype" class="blockelemtype" value="' + element[2] + '"><div class="grabme"><img src="assets/grabme.svg"></div><div class="blockin"><div class="blockico"><span></span><img src="assets/action.svg"></div><div class="blocktext"><p class="blocktitle">' + element[0] + '</p><p class="blockdesc">' + element[1] + '</p></div></div></div>'
    );

    arrayOfRequests.forEach (
        element => requestsSidePanelHtmlString += '<div class="blockelem create-flowy noselect" id="' + element[2] + '"><input type="hidden" name="blockelemtype" class="blockelemtype" value="' + element[2] + '"><div class="grabme"><img src="assets/grabme.svg"></div><div class="blockin"><div class="blockico"><span></span><img src="assets/error.svg"></div><div class="blocktext"><p class="blocktitle">' + element[0] + '</p><p class="blockdesc">' + element[1] + '</p></div></div></div>'
    );

    arrayOfIntereactions.forEach (
        element => interactionsSidePanelHtmlString += '<div class="blockelem create-flowy noselect" id="' + element[2] + '"><input type="hidden" name="blockelemtype" class="blockelemtype" value="' + element[2] + '"><div class="grabme"><img src="assets/grabme.svg"></div><div class="blockin"><div class="blockico"><span></span><img src="assets/eye.svg"></div><div class="blocktext"><p class="blocktitle">' + element[0] + '</p><p class="blockdesc">' + element[1] + '</p></div></div></div>'
    );


    // cards on canvas
    for (var i = 0; i < arrayOfItems.length; ++i) {
        dragElementsHtmlString[arrayOfItems[i][2]] = "<div class='blockyleft'><img src='assets/actionblue.svg'><p class='blockyname'>" + arrayOfItems[i][0] + "</p></div><div class='blockyright'><img src='assets/more.svg'></div><div class='blockydiv'></div><div class='blockyinfo'>" + arrayOfItems[i][1] + "</div><div class='showFlowChart'><img src='assets/flow-chart.svg'></div>";
    }

    for (var i = 0; i < arrayOfRequests.length; ++i) {
        dragElementsHtmlString[arrayOfRequests[i][2]] = "<div class='blockyleft'><img src='assets/errorblue.svg'><p class='blockyname'>" + arrayOfRequests[i][0] + "</p></div><div class='blockyright'><img src='assets/more.svg'></div><div class='blockydiv'></div><div class='blockyinfo'>" + arrayOfRequests[i][1] + "</div>";
    }

    for (var i = 0; i < arrayOfIntereactions.length; ++i) {
        dragElementsHtmlString[arrayOfIntereactions[i][2]] = "<div class='blockyleft'><img src='assets/eyeblue.svg'><p class='blockyname'>" + arrayOfIntereactions[i][0] + "</p></div><div class='blockyright'><img src='assets/more.svg'></div><div class='blockydiv'></div><div class='blockyinfo'>" + arrayOfIntereactions[i][1] + "</div>";
    }




    document.getElementById("blocklist").innerHTML = itemsSidePanelHtmlString;






    flowy(document.getElementById("canvas"), drag, release, snapping);
    function addEventListenerMulti(type, listener, capture, selector) {
        var nodes = document.querySelectorAll(selector);
        for (var i = 0; i < nodes.length; i++) {
            nodes[i].addEventListener(type, listener, capture);
        }
    }
    function snapping(drag, first) {
        var grab = drag.querySelector(".grabme");
        grab.parentNode.removeChild(grab);
        var blockin = drag.querySelector(".blockin");
        blockin.parentNode.removeChild(blockin);
        drag.innerHTML += dragElementsHtmlString[drag.querySelector(".blockelemtype").value];
        console.log(drag);

        return true;
    }
    function drag(block) {
        block.classList.add("blockdisabled");
        tempblock2 = block;
    }
    function release() {
        if (tempblock2) {
            tempblock2.classList.remove("blockdisabled");
        }
    }
    var disabledClick = function(){
        //get previous id of nav item to see what to save
        var itemsValuePrevious = document.getElementById("Items").classList.value;
        var requestsValuePrevious = document.getElementById("Requests").classList.value;
        var interactionsValuePrevious = document.getElementById("Interactions").classList.value;

        console.log(itemsValuePrevious);

        document.querySelector(".navactive").classList.add("navdisabled");
        document.querySelector(".navactive").classList.remove("navactive");



        if (this.getAttribute("id") == "Items") {
            if (requestsValuePrevious === 'side navactive') {
                requestsSidePanelHtmlString = document.getElementById("blocklist").innerHTML;
            } else if (interactionsValuePrevious === 'side navactive') {
                interactionsSidePanelHtmlString = document.getElementById("blocklist").innerHTML;
            } else {
                itemsSidePanelHtmlString = document.getElementById("blocklist").innerHTML;
            }
            document.getElementById("blocklist").innerHTML = itemsSidePanelHtmlString;
        } else if (this.getAttribute("id") == "Requests") {
            if (itemsValuePrevious === 'side navactive') {
                itemsSidePanelHtmlString = document.getElementById("blocklist").innerHTML;
            } else if (interactionsValuePrevious === 'side navactive') {
                interactionsSidePanelHtmlString = document.getElementById("blocklist").innerHTML;
            } else {
                itemsSidePanelHtmlString = document.getElementById("blocklist").innerHTML;
            }
            document.getElementById("blocklist").innerHTML = requestsSidePanelHtmlString;
        } else if (this.getAttribute("id") == "Interactions") {
            if (requestsValuePrevious === 'side navactive') {
                requestsSidePanelHtmlString = document.getElementById("blocklist").innerHTML;
            } else if (itemsValuePrevious === 'side navactive') {
                itemsSidePanelHtmlString = document.getElementById("blocklist").innerHTML;
            } else {
                itemsSidePanelHtmlString = document.getElementById("blocklist").innerHTML;
            }
            document.getElementById("blocklist").innerHTML = interactionsSidePanelHtmlString;
        }

        this.classList.add("navactive");
        this.classList.remove("navdisabled");
    }
    addEventListenerMulti("click", disabledClick, false, ".side");
    document.getElementById("close").addEventListener("click", function(){
       if (rightcard) {
           rightcard = false;
           document.getElementById("properties").classList.remove("expanded");
           setTimeout(function(){
                document.getElementById("propwrap").classList.remove("itson");
           }, 300);
            tempblock.classList.remove("selectedblock");
       }
    });

document.getElementById("removeblock").addEventListener("click", function(){
 flowy.deleteBlocks();
});
var aclick = false;
var noinfo = false;
var beginTouch = function (event) {
    aclick = true;
    noinfo = false;
    if (event.target.closest(".create-flowy")) {
        noinfo = true;
    }
}
var checkTouch = function (event) {
    aclick = false;
}
var doneTouch = function (event) {
    if (event.type === "mouseup" && aclick && !noinfo) {
        if (event.target.id === 'note') {
            tempblock = event.target.closest(".block");
            rightcard = true;
            document.getElementById("properties").classList.add("expanded");
            document.getElementById("propwrap").classList.add("itson");
            tempblock.classList.add("selectedblock");
        }

      if (!rightcard && event.target.closest(".block") && !event.target.closest(".block").classList.contains("dragging")) {
            tempblock = event.target.closest(".block");
            rightcard = true;
            tempblock.classList.add("selectedblock");
       }
    }
}
addEventListener("mousedown", beginTouch, false);
addEventListener("mousemove", checkTouch, false);
addEventListener("mouseup", doneTouch, false);
addEventListenerMulti("touchstart", beginTouch, false, ".block");

    document.querySelector('#notes').addEventListener('click', function() {
        createNoteHTMLelement();
    });

    // remove item where 'x' button is pressed
    document.querySelector('#canvas').addEventListener('click', function(event) {
        if (event.target.id === 'close-note') {
            event.target.parentNode.remove();
        }
    });

});


function createNoteHTMLelement() {
    // create the parent div for the note
    let noteDiv = document.createElement("div");
    noteDiv.classList.add('noteelem');
    noteDiv.classList.add('noselect');
    noteDiv.classList.add('block');
    noteDiv.setAttribute("id","note-div");
    noteDiv.setAttribute("style", "left: 0px; top: 80px; background-color: rgb(150, 150, 150)");

    // add delete button to created element
    let deletebutton = document.createElement("button");
    deletebutton.classList.add("note-delete-button");
    deletebutton.setAttribute("id", 'close-note');
    deletebutton.textContent = 'x';
    noteDiv.appendChild(deletebutton);

    // add input with hidden id so that element can be used with flowy.js
    let input = document.createElement("input");
    input.type = 'hidden';
    input.classList.add('noteid')
    input.value = 'note';
    noteDiv.appendChild(input);
    document.getElementById("canvas").appendChild(noteDiv);

    // Div to hold text of notes element
    let noteTextDiv = document.createElement("div");
    noteTextDiv.classList.add('blockyleft');
    noteTextDiv.setAttribute("style", "max-width: 200px; margin-bottom: 20px;");

    // Title element of div
    let noteTitle = document.createElement("p");
    noteTitle.setAttribute("contentEditable", true);
    noteTitle.classList.add("note-title");
    noteTitle.setAttribute("style", "max-width: 200px; color:white;");
    noteTitle.innerText = 'Click to edit note title';
    noteTextDiv.appendChild(noteTitle);

    // Detail element of div
    let noteDetail = document.createElement("p");
    noteDetail.setAttribute("contentEditable", true);
    noteDetail.classList.add("note-detail");
    noteDetail.setAttribute("style", "max-width: 200px; color:white;");
    noteDetail.innerText = 'Click to edit note detail';
    noteTextDiv.appendChild(noteDetail);

    noteDiv.appendChild(noteTextDiv);
}

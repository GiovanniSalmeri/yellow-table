// Table extension, https://github.com/GiovanniSalmeri/yellow-table

"use strict";
document.addEventListener("DOMContentLoaded", function() {

    /// filtrable

    function filterOn(input) {
        var filter = input.value.toLowerCase();
        var id = input.getAttribute("data-table");
        var table = document.getElementById(id);
        var rows = table.querySelectorAll("tbody tr");
        rows.forEach(function(row) {
            if (row.textContent.toLowerCase().indexOf(filter) > -1) {
                row.removeAttribute("aria-hidden");
            } else {
                row.setAttribute("aria-hidden", true);
            }
        });
    }

    var tables = document.querySelectorAll("table.filtrable");
    tables.forEach(function(table, i) {

        table.id = table.id  || "table-filtrable-"+i;
        var filterInput = document.querySelector("input.filter[data-table=\""+table.id+"\"]");
        if (!filterInput) {
            filterInput = document.createElement("input");
            filterInput.type = "text";
            filterInput.setAttribute("role", "search");
            filterInput.className = "filter form-control";
            filterInput.dataset["table"] = table.id;
            var tableCaption = table.querySelector("caption");
            if (!tableCaption) {
                tableCaption = document.createElement("caption");
                table.insertAdjacentElement("afterbegin", tableCaption);
            }
            tableCaption.insertAdjacentElement("beforeend", filterInput);
        }
        filterInput.addEventListener("input", function(e) { 
            // together with display: none emulate visibility: collapse
            var id = e.target.getAttribute("data-table");
            var table = document.getElementById(id);
            var headers = table.querySelectorAll("thead th");
            if (!headers[0].style.width) { // only if necessary
                headers.forEach(function(header) {
                    // minWidth instead of width avoids strange quirks in Blink
                    header.style.minWidth = window.getComputedStyle(header).getPropertyValue("width");
                });
            }
            filterOn(e.target); 
        }, false);
    });

    window.addEventListener("resize", function() {
        var headers = document.querySelectorAll("table.filtrable thead tr th");
        headers.forEach(function(header) {
            header.style.removeProperty("width");
        });
    });

    /// sortable

    function tableSort(header) {
        var table = header.closest("table");
        // get Table Data
        var rows = table.querySelectorAll("tbody tr");
        var tableData = [];
        rows.forEach(function(row, i) {
            tableData[i] = {};
            tableData[i]["index"] = i;
            for (var j = 0, m = row.cells.length; j < m; j++) {
                  tableData[i][j] = row.cells[j].innerText.trim();
            }
        });
        // sort Table Data
        var sortOrder = header.getAttribute("aria-sort") == "ascending" ? -1 : 1;
        var colNo = header.cellIndex;
        var coll = new Intl.Collator([document.documentElement.lang, "en"], { numeric: true, sensitivity: "base", ignorePunctuation: true }).compare;
        tableData.sort(function(a, b) { return coll(a[colNo], b[colNo])*sortOrder; });
        // rewrite Table HTML
        var html = "";
        tableData.forEach(function(line) {
            html += rows[line["index"]].outerHTML;
        });
        table.querySelector("tbody").innerHTML = html;
        // remove TH Class
        table.querySelectorAll("thead th").forEach(function(header) {
            header.removeAttribute("aria-sort");
        });
        // set TH Class
        var sortLabel = sortOrder === 1 ? "ascending" : "descending";
        header.setAttribute("aria-sort", sortLabel);
    }

    var tables = document.querySelectorAll("table.sortable");
    tables.forEach(function(table) {
        var ths = table.querySelectorAll("th");
        ths.forEach(function(th) {
            th.setAttribute("tabindex", 0);
        });
        table.setAttribute("role", "grid");
        var headers = table.querySelectorAll("thead tr");
        headers.forEach(function(header) {
            tableSort(header.cells[0]);
            for (var j = 0, m = header.cells.length; j < m; j++) {
                header.cells[j].addEventListener("click", function(e) { tableSort(e.currentTarget); }, false);
                header.cells[j].addEventListener("keydown", function(e) { if (e.which === 13) this.click(); }, false);
            }
        });
    });

    /// point-aligned

    var tables = document.querySelectorAll("table.point-aligned");
    tables.forEach(function(table) {
        for (var c = 1; ; c++) {
            var cells = table.querySelectorAll("td:nth-child(" + c + ")");
            if (!cells.length) break;
            var maxDec = 0;
            var thereAreDec = false;
            var numOfDec = {};
            cells.forEach(function(cell, i) {
                var content = cell.innerText.trim().replace("\u00A0", "");
                if (!isNaN(content) && content !== "") {
                    var dec = content.indexOf(".");
                    if (dec > -1) {
                        thereAreDec = true;
                        numOfDec[i] = content.length - dec - 1;
                        maxDec = Math.max(maxDec, numOfDec[i]);
                    } else {
                        numOfDec[i] = 0;
                    }
                }
            });
            cells.forEach(function(cell, i) {
                var padding = "";
                if (numOfDec[i] || thereAreDec) {
                    for (var d = numOfDec[i]; d < maxDec; d++) {
                        if (d % 3 == 0)  padding += "\u00A0"; // no-break space
                        padding += "\u2007";  // figure space
                    }
                }
                if (numOfDec[i]) {
                    cell.innerText += padding;
                } else if (thereAreDec) {
                    cell.innerText += "\u2008" + padding.substr(1); // punctuation space
                }
            });
        }
    });

    /// set tabindex (for accessibility)

    window.addEventListener("resize", function() {
        var divs = document.querySelectorAll("div.table-container");
        divs.forEach(function(div, i) {
            div.setAttribute("tabindex", div.scrollWidth > div.clientWidth ? 0 : null);
        });
    });
    window.dispatchEvent(new Event("resize"));

}, false);

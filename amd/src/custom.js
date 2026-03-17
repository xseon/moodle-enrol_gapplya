// This file is part of Moodle - https://moodle.org/
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
 * @module     enrol_gapplya/custom
 * @copyright  2026 Dimitar Mitev <info@napravisisait.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    "jquery",
    "enrol_gapplya/jszip",
    "core/toast",
    "core/ajax",
    "enrol_gapplya/jquery.dataTables",
    "enrol_gapplya/dataTables.bootstrap4",
    "enrol_gapplya/dataTables.select",
    "enrol_gapplya/select.bootstrap4",
    "enrol_gapplya/dataTables.buttons",
    "enrol_gapplya/buttons.bootstrap4",
    "enrol_gapplya/buttons.html5",
    "enrol_gapplya/dataTables.rowGroup",
    "enrol_gapplya/rowGroup.bootstrap4",
    "enrol_gapplya/buttons.colVis"
], function($, JSZip, toast, Ajax) {

    window.JSZip = JSZip;

    return {
        init: function(tab, id) {

            // --- Loading Helper ---
            if (!$("#enrol-gapplya-loading").length) {
                $("body").append('<div id="enrol-gapplya-loading" class="d-none align-items-center justify-content-center position-fixed w-100 h-100" style="top: 0;bottom: 0; left: 0; right: 0; z-index: 9999; background: rgba(0,0,0,0.5);"> <div class="spinner-grow text-light" style="width: 3rem; height: 3rem;" role="status"> <span class="sr-only">Loading...</span></div></div>');
            }
            const showLoading = () => { $("#enrol-gapplya-loading").removeClass("d-none").addClass("d-flex"); };
            const hideLoading = () => { $("#enrol-gapplya-loading").removeClass("d-flex").addClass("d-none"); };

            // --- Modal Helpers ---
            const removeModal = (modalId) => {
                const el = document.getElementById(modalId);
                if (el && typeof bootstrap !== "undefined" && bootstrap.Modal) {
                    const instance = bootstrap.Modal.getInstance(el);
                    if (instance) {
                        instance.dispose();
                    }
                }
                $("#" + modalId).remove();
            };

            const showModal = (modalId) => {
                const el = document.getElementById(modalId);
                if (el) {
                    if (typeof bootstrap !== "undefined" && bootstrap.Modal) {
                        (bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el)).show();
                    } else {
                        $("#" + modalId).modal("show");
                    }
                }
            };

            const hideModal = (modalId) => {
                const el = document.getElementById(modalId);
                if (el) {
                    if (typeof bootstrap !== "undefined" && bootstrap.Modal) {
                        (bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el)).hide();
                    } else {
                        $("#" + modalId).modal("hide");
                    }
                }
            };

            const initTooltips = () => {
                const selector = '[data-toggle="tooltip"], [data-bs-toggle="tooltip"], .dt-button';
                if (typeof bootstrap !== "undefined" && bootstrap.Tooltip) {
                    document.querySelectorAll(selector).forEach(function(el) {
                        if (!el.getAttribute("data-bs-title") && el.getAttribute("title")) {
                            el.setAttribute("data-bs-title", el.getAttribute("title"));
                            el.removeAttribute("title");
                        }
                        const t = bootstrap.Tooltip.getInstance(el);
                        if (t) {
                            t.dispose();
                        }
                        new bootstrap.Tooltip(el);
                    });
                } else {
                    $(selector).tooltip("dispose").tooltip({ container: "body", trigger: "hover" });
                }
            };

            // --- File Preview Logic ---
            $(document).off("click", "a[data-type]").on("click", "a[data-type]", function() {
                let modalHtml = '<div class="modal fade" id="applyfile" data-backdrop="static" data-keyboard="false" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title flex-grow-1" id="applyfileLabel"></h5><button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal"><i class="fa fa-times"></i></button></div><div class="modal-body p-0 text-center d-flex justify-content-center"></div><div class="modal-footer"><a href="javascript:void(0)" id="forcedownloadbutton" class="btn btn-primary text-uppercase font-weight-bold">' + M.util.get_string("download", "enrol_gapplya") + '</a><button type="button" class="btn btn-secondary text-uppercase font-weight-bold" data-dismiss="modal" data-bs-dismiss="modal">' + M.util.get_string("close", "enrol_gapplya") + "</button></div></div></div></div>";
                removeModal("applyfile");
                $("body").append(modalHtml);
                $("#applyfileLabel").html($(this).text());
                showModal("applyfile");

                let content = "", type = $(this).data("type"), url = $(this).data("url");
                if (type.includes("image")) {
                    content = '<img src="' + url + '" class="img-fluid mx-auto">';
                    $("#applyfile .modal-body").removeClass("d-flex");
                } else if (type.includes("video")) {
                    content = '<video src="' + url + '" class="embed-responsive-item m-0" controls width="100%" autoplay></video>';
                } else if (type.includes("audio")) {
                    content = '<audio src="' + url + '" class="embed-responsive-item m-0" controls width="100%" autoplay></audio>';
                } else if (type.includes("pdf")) {
                    content = '<object data="' + url + '" type="application/pdf" width="100%" style="height: 80vh"><p>' + M.util.get_string("cannotopenpdffile", "enrol_gapplya", url) + '</p></object>';
                } else if (type.includes("officedocument") || type.includes("msword") || type.includes("openxmlformats")) {
                    content = '<iframe src="https://view.officeapps.live.com/op/embed.aspx?src=' + url + '" class="embed-responsive-item" style="width: 100%; height: 80vh"></iframe>';
                } else if (type.includes("text") || type.includes("csv")) {
                    content = '<iframe src="https://docs.google.com/viewer?url=' + url + '&embedded=true" class="embed-responsive-item" style="width: 100%; height: 80vh; border-radius: 0"></iframe>';
                } else {
                    content = '<p class="text-center py-5">' + M.util.get_string("cannotopenfile", "enrol_gapplya", url) + '</p>';
                    $("#applyfile .modal-body").removeClass("d-flex");
                }
                $("#applyfile .modal-body").html(content);

                let fileURL;
                try {
                    fileURL = new URL(url, window.location.origin);
                } catch (e) {
                    toast.add(M.util.get_string("anerroroccurred", "enrol_gapplya"), { type: "danger" });
                    return;
                }
                $("#applyfile").off("click", "#forcedownloadbutton").on("click", "#forcedownloadbutton", function() {
                    fileURL.searchParams.set("forcedownload", 1);
                    window.open(fileURL.toString());
                });
            });

            // --- Table Config ---
            const timecreatedIndex = $("th").index($("th.timecreated"));
            const renderFilterBox = (index, text) => `<div class="col-sm-6 col-md-4 col-lg-3 col-xl-2 pl-0 pr-2"><div class="form-group mb-1"><label for="filter-${index}">${text}</label><input type="text" class="form-control form-control-sm" id="filter-${index}" data-index="${index}"/></div></div>`;

            let option = {
                ajax: {
                    url: M.cfg.wwwroot + "/enrol/gapplya/ajax.php?id=" + id + "&action=getapplications&tab=" + tab + "&sesskey=" + M.cfg.sesskey,
                    dataSrc: function(json) { return json; }
                },
                select: {
                    style: 'multi',
                    selector: 'td.select-checkbox'
                },
                deferRender: true,
                createdRow: function(row) {
                    if ($(row).hasClass('status-approved')) {
                        $(row).find('td:first-child').removeClass('select-checkbox');
                    }
                },
                dom: "<'d-flex align-items-start justify-content-between'<'d-flex align-items-start'B><'d-flex align-items-center'fl>><'#filterregion.w-100 row mx-0 mt-2'>t<'row'<'col-sm-6'i><'col-sm-6'p>>",
                stateSave: true,
                stateLoadParams: function(settings, data) {
                    if (data.columns.length !== $("#gapplytable th").length) return false;
                },
                buttons: (function() {
                    const exportLogic = function (idx, data, node) {
                        let $node = $(node);
                        if ($node.hasClass('noorder') || $node.hasClass('userdetails') || $node.hasClass('applicationdetails')) {
                            return false;
                        }
                        if ($node.hasClass('export-only') || $node.hasClass('timecreated')) return true;
                        if ($node.hasClass('applicationtext')) {
                            return $("#gapplytable th.applicationdetails").length > 0;
                        }
                        return $node.is(':visible');
                    };

                    const commonExportOptions = {
                        modifier: { selected: null },
                        columns: exportLogic
                    };

                    return [
                        { extend: "copyHtml5", className: "btn btn-sm btn-alt-primary", text: '<i class="fa fa-copy fa-fw"></i>', titleAttr: M.util.get_string("copy", "enrol_gapplya"), title: "", attr: { 'data-toggle': 'tooltip', 'data-bs-toggle': 'tooltip' }, exportOptions: commonExportOptions },
                        { extend: "csvHtml5", className: "btn btn-sm btn-alt-primary", text: '<i class="fa fa-file-code-o fa-fw"></i>', titleAttr: M.util.get_string("csv", "enrol_gapplya"), title: "", attr: { 'data-toggle': 'tooltip', 'data-bs-toggle': 'tooltip' }, exportOptions: commonExportOptions },
                        { extend: "excelHtml5", className: "btn btn-sm btn-alt-primary", text: '<i class="fa fa-file-excel-o fa-fw"></i>', titleAttr: M.util.get_string("excel", "enrol_gapplya"), title: "", sheetName: M.util.get_string(tab, 'enrol_gapplya').substring(0, 31).replace(/[\[\]*\/\\?:]/g, ''), attr: { 'data-toggle': 'tooltip', 'data-bs-toggle': 'tooltip' }, exportOptions: commonExportOptions },
                        { extend: "colvis", className: "btn btn-sm btn-alt-primary buttons-colvis", columns: ".colvis", text: '<i class="fa fa-columns fa-fw"></i>', titleAttr: M.util.get_string("columns", "enrol_gapplya"), attr: { 'data-toggle': 'tooltip', 'data-bs-toggle': 'tooltip' }, prefixButtons: [{ text: M.util.get_string("all", "enrol_gapplya"), className: 'dropdown-item font-weight-bold text-danger border-bottom pb-2 mb-2', action: function (e, dt) { dt.columns('.colvis').visible(true, false); dt.columns.adjust().draw(false); rebuildFilters(dt); } }], postfixButtons: [{ text: M.util.get_string("clear", "enrol_gapplya"), className: 'dropdown-item font-weight-bold text-danger border-top mt-2 pt-2', action: function (e, dt) { dt.columns('.colvis').visible(false, false); dt.columns.adjust().draw(false); rebuildFilters(dt); } }] }
                    ];
                })(),
                language: {
                    lengthMenu: "_MENU_",
                    zeroRecords: M.util.get_string("nofound", "enrol_gapplya"),
                    search: M.util.get_string("search", "enrol_gapplya"),
                    info: M.util.get_string("datatableinfo", "enrol_gapplya"),
                    infoEmpty: M.util.get_string("datatableinfoempty", "enrol_gapplya"),
                    infoFiltered: M.util.get_string("datatableinfofiltered", "enrol_gapplya"),
                    paginate: { first: M.util.get_string("first", "enrol_gapplya"), last: M.util.get_string("last", "enrol_gapplya"), next: M.util.get_string("next", "enrol_gapplya"), previous: M.util.get_string("previous", "enrol_gapplya") },
                    select: { rows: { _: M.util.get_string("rowsselected", "enrol_gapplya") } }
                },
                order: [[timecreatedIndex, "desc"]],
                columnDefs: [
                    { targets: "inv", visible: false },
                    { targets: "export-only", visible: false, searchable: false },
                    { targets: 0, className: "select-checkbox text-center", orderable: false },
                    { targets: "userdetails", className: "text-truncate" },
                    { targets: "col-actions", className: "text-center", orderable: false },
                    { targets: "status", visible: (tab === "all") }
                ],
                initComplete: function() {
                    let dt = this.api();
                    dt.column('.status').visible(tab === 'all');

                    $("#gapplytable").wrap('<div style="overflow-x:auto;"></div>');
                    $(".dataTables_filter").addClass("mb-0 mr-3");
                    $(".dataTables_filter label").addClass("mb-0 d-flex align-items-center");
                    $(".dataTables_filter input").addClass("ml-2");

                    $(".dataTables_length").addClass("mb-0");
                    $(".dataTables_length label").addClass("mb-0 d-flex align-items-center");
                    $(".dataTables_length select").attr({ "data-toggle": "tooltip", "data-bs-toggle": "tooltip", "title": M.util.get_string("recordsperpage", "enrol_gapplya") });

                    $("#gapplytable").addClass("mx-n1").removeClass("d-none");

                    $('<a class="btn btn-sm btn-secondary font-weight-bold ml-1" href="javascript:void(0)" id="filters" data-toggle="tooltip" data-bs-toggle="tooltip" title="' + M.util.get_string("filter", "enrol_gapplya") + '"><i class="fa fa-filter left fa-fw"></i></a>').insertAfter(".dataTables_filter label");
                    $(document).off("click", "#filters").on("click", "#filters", function() {
                        $("#filterregion").slideToggle("fast");
                    });
                    $("#filterregion").css("display", "none");

                    $('<div class="dropdown d-inline right small"><button class="btn btn-sm btn-secondary dropdown-toggle font-weight-bold ml-1" id="dropdownMenuButton" data-toggle="dropdown"><i class="fa fa-sort fa-fw" title="' + M.util.get_string("sort", "enrol_gapplya") + '" data-toggle="tooltip" data-bs-toggle="tooltip"></i></button><div class="dropdown-menu dropdown-menu-right" id="sortdropdown"></div></div>').insertAfter("#filters");

                    // Initial build of filters based on visible columns
                    rebuildFilters(dt);
                    initTooltips();
                }
            };

            let table = $("#gapplytable").DataTable(option);

            // Rebuilds filter and sort dropdowns dynamically based on current column visibility.
            const rebuildFilters = (dt) => {
                $("#filterregion").empty();
                $("#sortdropdown").empty();

                dt.columns().every(function(index) {
                    let header = $(this.header());
                    let isIgnored = header.hasClass("noorder") || header.hasClass("export-only") || header.hasClass("d-none");

                    // Only process currently visible columns, ignoring checkboxes, actions and hidden technical cols.
                    if (this.visible() && !isIgnored) {
                        let text = header.text().trim();
                        if (text !== "") {
                            $(renderFilterBox(index, text)).appendTo("#filterregion");

                            let sortItem = '<a class="dropdown-item" href="javascript:void(0)" data-col="' + index + '">';
                            sortItem += text + "</a>";
                            $("#sortdropdown").append(sortItem);
                        }
                    }
                });

                // Append sort directions at the bottom.
                let descText = M.util.get_string("desc", "enrol_gapplya");
                let ascText = M.util.get_string("asc", "enrol_gapplya");
                let appendHtml = '<div class="dropdown-divider"></div>';
                appendHtml += '<a class="dropdown-item active" href="javascript:void(0)" data-order="desc">' + descText + '</a>';
                appendHtml += '<a class="dropdown-item" href="javascript:void(0)" data-order="asc">' + ascText + '</a>';

                $("#sortdropdown").append(appendHtml);

                // Re-apply existing search values to the new filter boxes if any.
                $("#filterregion input").each(function() {
                    let colIdx = $(this).data("index");
                    let currentSearch = dt.column(colIdx).search();
                    if (currentSearch) {
                        $(this).val(currentSearch);
                    }
                });
            };

            // Listen for column visibility changes from the "ColVis" button and rebuild filters
            table.on('column-visibility.dt', function (e, settings, column, state) {
                rebuildFilters(table);
            });

            $("body").on("keyup", "#filterregion input", function() {
                table.column($(this).data("index")).search($(this).val(), false, true).draw();
            });

            $("body").on("click", "#sortdropdown.dropdown-menu a", function() {
                if ($(this).data("order")) {
                    $(".dropdown-menu a[data-order]").removeClass("active");
                    $(this).addClass("active");
                } else {
                    $(".dropdown-menu a[data-col]").removeClass("active");
                    $(this).addClass("active");
                }
                const col = $(".dropdown-menu a[data-col].active").data("col");
                const order = $(".dropdown-menu a[data-order].active").data("order");
                table.order([col, order]).draw();
            });

            let selecteddata = [];
            const updateBulkButtons = (dt) => {
                $(".bulk-actions-container").remove();
                selecteddata = dt.rows({ selected: true }).ids().toArray();
                if (selecteddata.length > 0) {
                    let btns = '<div class="bulk-actions-container ml-3">';
                    btns += '<button class="btn btn-sm btn-success action-button mr-1" data-action="approve" data-toggle="tooltip" title="' + M.util.get_string("approve", "enrol_gapplya") + '"><i class="fa fa-fw fa-check"></i></button>';
                    btns += '<button class="btn btn-sm btn-info action-button mr-1" data-action="waitlist" data-toggle="tooltip" title="' + M.util.get_string("waitlist", "enrol_gapplya") + '"><i class="fa fa-fw fa-clock-o"></i></button>';
                    btns += '<button class="btn btn-sm btn-warning action-button mr-1" data-action="reject" data-toggle="tooltip" title="' + M.util.get_string("reject", "enrol_gapplya") + '"><i class="fa fa-fw fa-times"></i></button>';
                    btns += '<button class="btn btn-sm btn-secondary action-button mr-1" data-action="withdraw" data-toggle="tooltip" title="' + M.util.get_string("withdraw", "enrol_gapplya") + '"><i class="fa fa-fw fa-ban"></i></button>';
                    btns += '<button class="btn btn-sm btn-dark action-button mr-1" data-action="sendmessage" data-toggle="tooltip" title="' + M.util.get_string("sendmessage", "enrol_gapplya") + '"><i class="fa fa-fw fa-envelope"></i></button>';
                    btns += '<button class="btn btn-sm btn-danger action-button" data-action="delete" data-toggle="tooltip" title="' + M.util.get_string("delete", "enrol_gapplya") + '"><i class="fa fa-fw fa-trash"></i></button>';
                    btns += '</div>';

                    $(".dt-buttons").append(btns);
                    initTooltips();
                }
            };
            table.on("select deselect", function(e, dt) { updateBulkButtons(dt); });

            // --- Action Logic ---
            $(document).on("click", ".action-button", async function() {
                const action = $(this).data("action");
                let btnClass = (action === "waitlist" ? "btn-info" : (action === "reject" ? "btn-warning" : (action === "delete" ? "btn-danger" : (action === "withdraw" ? "btn-secondary" : "btn-primary"))));
                if (action === "approve") btnClass = "btn-success";

                if ($(this).hasClass("menu-action")) {
                    selecteddata = [$(this).data("id")];
                }

                let roleoptions = "", groupoptions = "", startdate = "", enddate = "", extraInputs = "";
                let showNotifyCheckbox = true;

                if (action === "sendmessage") {
                    showNotifyCheckbox = false;
                    let courseName = $(".breadcrumb-item a[href*='course/view.php']").last().text().trim() || $("h1").text().trim() || "";
                    let defaultSubject = M.util.get_string("custommsgsubject", "enrol_gapplya", courseName).replace(/"/g, '&quot;');
                    extraInputs = '<div class="form-group mt-3"><label class="font-weight-bold">' + M.util.get_string("messagesubject", "enrol_gapplya") + '</label><input type="text" class="form-control" id="messagesubject" value="' + defaultSubject + '" required></div>';
                    extraInputs += '<div class="form-group mt-3"><label class="font-weight-bold">' + M.util.get_string("messagetext", "enrol_gapplya") + '</label><textarea class="form-control" id="messagetext" rows="4" required></textarea>';
                    extraInputs += '<small class="form-text text-muted">' + M.util.get_string("messagehtmlhelp", "enrol_gapplya") + '</small></div>';
                } else if (action === "approve") {
                    try {
                        let groupsReq = Ajax.call([{ methodname: 'enrol_gapplya_get_groups', args: { instanceid: $("#gapplytable").data("instance") } }])[0];
                        let rolesReq = Ajax.call([{ methodname: 'enrol_gapplya_get_roles_and_dates', args: { instanceid: $("#gapplytable").data("instance") } }])[0];

                        let groupsStr = await groupsReq;
                        let rolesStr = await rolesReq;

                        let groups = JSON.parse(groupsStr);
                        let roles = JSON.parse(rolesStr);

                        if (groups.length > 0) {
                            let ghtml = "";
                            groups.forEach(g => {
                                ghtml += '<div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input groups" id="group-' + g.id + '" name="groups[]" value="' + g.id + '"><label class="custom-control-label" for="group-' + g.id + '">' + g.name + '</label></div>';
                            });
                            groupoptions = '<div class="form-group mt-3"><label>' + M.util.get_string("assigngroups", "enrol_gapplya") + '</label><div>' + ghtml + '</div></div>';
                        }
                        let rhtml = '<div class="form-group mt-3"><label>' + M.util.get_string("assignrole", "enrol_gapplya") + '</label><select class="custom-select w-100" id="role" name="role">';
                        Object.keys(roles.roles).forEach(rid => {
                            rhtml += '<option value="' + rid + '" ' + (rid === roles.defaultrole.toString() ? "selected" : "") + '>' + roles.roles[rid] + '</option>';
                        });
                        roleoptions = rhtml + '</select></div>';
                        const fmtDate = (ts) => ts ? new Date(ts * 1000).toISOString().slice(0, 16) : "";
                        startdate = '<div class="form-group mt-3"><label>' + M.util.get_string("startdate", "enrol_gapplya") + '</label><input type="datetime-local" class="form-control w-100" id="startdate" value="' + fmtDate(roles.startdate) + '"></div>';
                        enddate = '<div class="form-group mt-3"><label>' + M.util.get_string("enddate", "enrol_gapplya") + '</label><input type="datetime-local" class="form-control w-100" id="enddate" value="' + fmtDate(roles.enddate) + '"></div>';
                    } catch (e) {
                        toast.add(M.util.get_string("anerroroccurred", "enrol_gapplya"), { type: "danger" });
                        return;
                    }
                }
                let notifyHtml = "";
                if (showNotifyCheckbox) {
                    notifyHtml = '<div class="custom-control custom-checkbox mt-4"><input type="checkbox" class="custom-control-input" id="notifyusers" checked><label class="custom-control-label" for="notifyusers">' + M.util.get_string("notifyusers", "enrol_gapplya") + '</label></div>';
                }

                let modal = '<div class="modal fade" id="approveModal" tabindex="-1" role="dialog" aria-hidden="true" style="background: rgba(0,0,0,0.5);"> <div class="modal-dialog modal-dialog-centered" role="document"> <div class="modal-content"> <div class="modal-header"> <h5 class="modal-title flex-grow-1">' + M.util.get_string(action + "applications", "enrol_gapplya") + '</h5> <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal"><i class="fa fa-fw fa-times"></i></button> </div> <div class="modal-body"> <p class="mb-0">' + M.util.get_string("areyousureyouwantto" + action, "enrol_gapplya") + "</p> " + roleoptions + " " + startdate + " " + enddate + " " + groupoptions + extraInputs + notifyHtml + ' </div> <div class="modal-footer"> <button type="button" class="btn btn-secondary text-uppercase font-weight-bold" data-dismiss="modal" data-bs-dismiss="modal">' + M.util.get_string("cancel", "enrol_gapplya") + '</button> <button type="button" class="btn ' + btnClass + ' text-uppercase font-weight-bold" id="proceed">' + M.util.get_string("proceed", "enrol_gapplya") + "</button> </div> </div> </div></div>";

                removeModal("approveModal");
                $("body").append(modal);
                showModal("approveModal");

                $("#approveModal #proceed").off("click").on("click", function() {
                    let msgText = $("#approveModal #messagetext").val() || "";
                    if (action === "sendmessage" && msgText.trim() === "") {
                        toast.add(M.util.get_string("required", "core"), { type: "danger" });
                        return;
                    }

                    hideModal("approveModal");
                    showLoading();

                    let isNotifying = $("#approveModal #notifyusers").length ? ($("#approveModal #notifyusers").is(":checked") ? 1 : 0) : 1;

                    let requestArgs = {
                        action: action,
                        instanceid: $("#gapplytable").data("instance"),
                        ids: selecteddata.map(Number),
                        groups: $("#approveModal input.groups:checked").map(function() { return Number(this.value); }).get(),
                        roleid: Number($("#approveModal select#role").val() || 0),
                        start: $("#approveModal input#startdate").val() !== "" ? Math.floor(new Date($("#approveModal input#startdate").val()).getTime() / 1000) : 0,
                        end: $("#approveModal input#enddate").val() !== "" ? Math.floor(new Date($("#approveModal input#enddate").val()).getTime() / 1000) : 0,
                        notify: isNotifying,
                        messagetext: msgText,
                        messagesubject: $("#approveModal #messagesubject").val() || ""
                    };

                    Ajax.call([{
                        methodname: 'enrol_gapplya_execute_action',
                        args: requestArgs
                    }])[0].done(function(r) {
                        if (r.trim() === "success") {
                            toast.add(M.util.get_string(action + "success", "enrol_gapplya"), { type: "success" });
                            setTimeout(() => window.location.reload(), 800);
                        } else {
                            toast.add(M.util.get_string("anerroroccurred", "enrol_gapplya"), { type: "danger" });
                        }
                    }).fail(function() {
                        toast.add(M.util.get_string("anerroroccurred", "enrol_gapplya"), { type: "danger" });
                    }).always(function() {
                        hideLoading();
                        table.rows().deselect();
                    });
                });
            });

            $(document).off("click", ".showuserdetail").on("click", ".showuserdetail", function() {
                showLoading();
                const userid = $(this).data("userid");
                const appid = $(this).data("id");
                const sts = $(this).data("status");
                const statusFormatted = $(this).data("statusformatted");
                selecteddata = [appid];
                const apptext = $(".applicationtext[data-id='" + appid + "']").html() || '';

                let tr = $(this).closest("tr");
                if (tr.hasClass("child")) {
                    tr = tr.prev();
                }
                let extraRowsHtml = "";
                const hardcoded_ids = ["department", "institution", "city", "phone1", "phone2", "email"];

                table.columns().every(function(index) {
                    let header = $(this.header());
                    if (this.visible() && header.hasClass("profilefield") && !header.hasClass("applicationdetails")) {
                        let isHardcoded = false;
                        hardcoded_ids.forEach(id => {
                            if (header.hasClass(id)) {
                                isHardcoded = true;
                            }
                        });
                        if (!isHardcoded) {
                            let title = header.text().trim();
                            let data = $(this.cell(tr, index).node()).text().trim();
                            if (data && data !== "" && data !== "-") {
                                extraRowsHtml += '<tr><th style="width: 35%; color: #333;">' + title + "</th><td>" + data + "</td></tr>";
                            }
                        }
                    }
                });

                $.ajax({
                    url: M.cfg.wwwroot + "/enrol/gapplya/ajax.php?action=getuserbyid&id=" + id + "&userid=" + userid + "&sesskey=" + M.cfg.sesskey,
                    type: "GET",
                    dataType: "text",
                    success: function(response) {
                        let modalHtml = '<div class="modal fade p-0" id="userdetailModal" tabindex="-1" role="dialog" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable" role="document" style="max-width: calc(100% - 2rem); height: calc(100% - 3.5rem);"><div class="modal-content">' + response + '</div></div></div>';

                        removeModal("userdetailModal");
                        $("body").append(modalHtml);

                        $("#dynamic-table-rows").html(extraRowsHtml);
                        $("#userdetailModal #applicationtext").html(apptext);
                        $("#userdetailModal #currentstatus").html(statusFormatted);

                        initTooltips();

                        showModal("userdetailModal");

                        setTimeout(function() {
                            hideLoading();
                        }, 250);

                        $("#userdetailModal").on("click", ".action-button", function() {
                            $(this).addClass("menu-action");
                        });

                        $("#userdetailModal").off("click", "#btn-toggle-edit").on("click", "#btn-toggle-edit", function() {
                            let v = $("#view-mode-container"), e = $("#edit-mode-container"), btn = $(this);
                            if (v.css("display") === "none") {
                                v.show();
                                e.hide();
                                btn.html('<i class="fa fa-pencil"></i> ' + M.util.get_string("edit", "enrol_gapplya"));
                            } else {
                                v.hide();
                                e.show();
                                btn.html('<i class="fa fa-times"></i> ' + M.util.get_string("cancel", "enrol_gapplya"));
                            }
                        });

                        const updateDependencies = () => {
                            $("#userdetailModal tr[data-dep-element]").each(function() {
                                let dependentRow = $(this);
                                let parentName = dependentRow.data('dep-element');
                                let condition = dependentRow.data('dep-condition');
                                let targetValue = dependentRow.data('dep-value');

                                let parentInput = $("#userdetailModal [name='edit_" + parentName + "']");
                                if (!parentInput.length) return;

                                let parentVal;
                                if (parentInput.is(':checkbox')) {
                                    parentVal = parentInput.is(':checked') ? 'yes' : 'no';
                                } else {
                                    parentVal = parentInput.val();
                                }

                                let isVisible = false;
                                if (condition === 'eq') {
                                    isVisible = (parentVal === targetValue);
                                } else if (condition === 'neq') {
                                    isVisible = (parentVal !== targetValue);
                                }

                                if (isVisible) {
                                    dependentRow.show();
                                } else {
                                    dependentRow.hide();

                                    let childInput = dependentRow.find("input, select, textarea");
                                    if (childInput.is(':checkbox') || childInput.is(':radio')) {
                                        childInput.prop('checked', false);
                                    } else {
                                        childInput.val('');
                                    }
                                }
                            });
                        };

                        updateDependencies();

                        $("#userdetailModal").off("change.dependencies").on("change.dependencies", "#edit-mode-container input, #edit-mode-container select", function() {
                            updateDependencies();
                        });

                        const openHistorySection = () => {
                            let hs = document.getElementById("history-section");
                            let hc = document.getElementById("history-content");
                            let hi = document.getElementById("history-caret");
                            let mb = document.querySelector("#userdetailModal .modal-body");

                            if (hs && hc && mb) {
                                hc.style.display = 'block';
                                if (hi) {
                                    hi.className = 'fa fa-caret-up ml-1';
                                }
                                $(mb).animate({ scrollTop: hs.offsetTop - 20 }, 600);
                            }
                        };

                        $("#userdetailModal").off("click", ".history-toggle").on("click", ".history-toggle", function() {
                            let hc = document.getElementById("history-content");
                            if (hc.style.display === 'none') {
                                openHistorySection();
                            } else {
                                hc.style.display = 'none';
                                let hi = document.getElementById("history-caret");
                                if (hi) {
                                    hi.className = 'fa fa-caret-down ml-1';
                                }
                            }
                        });

                        $("#userdetailModal").off("click", ".history-trigger").on("click", ".history-trigger", function() {
                            openHistorySection();
                        });

                        if (userid.toString().indexOf("_history") !== -1) {
                            setTimeout(function() {
                                openHistorySection();
                            }, 600);
                        }

                        // --- Save Changes ---
                        $("#userdetailModal").off("click", "#btn-save-changes").on("click", "#btn-save-changes", function() {
                            let btn = $(this);
                            let sp = $("#save-status");

                            btn.prop("disabled", true).html('<i class="fa fa-spinner fa-spin"></i>');
                            sp.html("");

                            let formDataObj = {};
                            let fd = new FormData(document.getElementById("gapplya-edit-form"));
                            fd.forEach((value, key) => { formDataObj[key] = value; });

                            // Checkboxes are tricky in FormData, explicitly add them if they are in the DOM
                            $("#gapplya-edit-form input[type='checkbox']").each(function() {
                                formDataObj[$(this).attr('name')] = $(this).is(':checked') ? 'yes' : 'no';
                            });

                            let requestArgs = {
                                instanceid: Number(fd.get("id")),
                                recordid: Number(fd.get("recordid")),
                                adminnote: fd.get("adminnote") || "",
                                formdata: JSON.stringify(formDataObj)
                            };

                            Ajax.call([{
                                methodname: 'enrol_gapplya_save_data',
                                args: requestArgs
                            }])[0].done(function(r) {
                                if (r && r.indexOf("success") !== -1) {
                                    sp.html('<span class="text-success">' + M.util.get_string("saved", "enrol_gapplya") + "</span>");
                                    setTimeout(function() {
                                        hideModal("userdetailModal");
                                        window.location.reload();
                                    }, 800);
                                } else {
                                    sp.html('<span class="text-danger">' + M.util.get_string("error", "enrol_gapplya") + "</span>");
                                    btn.prop("disabled", false).html('<i class="fa fa-save"></i> ' + M.util.get_string("savechanges", "enrol_gapplya"));
                                }
                            }).fail(function() {
                                sp.html('<span class="text-danger">' + M.util.get_string("anerroroccurred", "enrol_gapplya") + "</span>");
                                btn.prop("disabled", false).html('<i class="fa fa-save"></i> ' + M.util.get_string("savechanges", "enrol_gapplya"));
                            });
                        });

                        $("#userdetailModal").off("change", "#fileselect").on("change", "#fileselect", function() {
                            let url = $(this).val(), type = $(this).find(":selected").data("type"), name = $(this).find(":selected").text(), content = "";
                            if (type.includes("image")) {
                                content = '<img src="' + url + '" class="img-fluid mx-auto">';
                            } else if (type.includes("video")) {
                                content = '<video src="' + url + '" class="embed-responsive-item m-0" controls width="100%" autoplay></video>';
                            } else if (type.includes("audio")) {
                                content = '<audio src="' + url + '" class="embed-responsive-item m-0" controls width="100%" autoplay></audio>';
                            } else if (type.includes("pdf")) {
                                content = '<object data="' + url + '" type="application/pdf" width="100%" style="height: calc(100% - 7px);"><p>' + M.util.get_string("cannotopenpdffile", "enrol_gapplya", url) + '</p></object>';
                            } else if (type.includes("officedocument") || type.includes("msword") || type.includes("openxmlformats")) {
                                content = '<iframe src="https://view.officeapps.live.com/op/embed.aspx?src=' + url + '" class="embed-responsive-item" style="width: 100%; height: calc(100% - 7px);"></iframe>';
                            } else if (type.includes("text") || type.includes("csv")) {
                                content = '<iframe src="https://docs.google.com/viewer?url=' + url + '&embedded=true" class="embed-responsive-item" style="width: 100%; height: calc(100% - 7px); border-radius: 0"></iframe>';
                            } else {
                                content = '<p class="text-center py-5">' + M.util.get_string("cannotopenfile", "enrol_gapplya", url) + '</p>';
                            }
                            $(".fileview #viewer").html(content);
                            $("#userdetailmodalLabel").html(name);
                            $("#downloadbutton").attr("href", url);
                        });
                    },
                    error: function() {
                        toast.add(M.util.get_string("anerroroccurred", "enrol_gapplya"), { type: "danger" });
                        hideLoading();
                    }
                });
            });
        }
    };
});

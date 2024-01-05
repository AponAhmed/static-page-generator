
class keyWordManage {
    constructor({ ...options }) {
        this.listDom = options.listDom;//dom
        this.textArea = options.textarea;//dom
        this.newListBtn = options.newList;//dom
        this.updateBtn = options.updateBtn;//dom
        this.editor = false;
        this.list = false;
    }

    init() {
        this.setEvent();
        this.editorInit();
    }

    editorInit() {
        this.editor = wp.codeEditor.initialize(this.textArea, cm_settings).codemirror;
        this.editor.setSize(null, 500);
    }

    setEvent() {
        //Existing Lists
        this.listDom.find('li').each((i, e) => {
            this.listEvent(e);
        })
        //New List Create 
        this.newListBtn.on('click', () => {
            let newEl = document.createElement('li');
            //Span close
            let cls = document.createElement('span');
            cls.classList.add('removeList');
            cls.innerHTML = '&times;';

            let nme = document.createElement('span');
            nme.setAttribute('contenteditable', true);
            nme.innerHTML = 'Type here';
            nme.addEventListener('keyup', (e) => {
                //console.log(e.target);
                newEl.setAttribute('data-name', e.target.textContent);
            });
            newEl.classList.add('newItem');
            newEl.appendChild(nme);
            newEl.appendChild(cls);
            this.listEvent(newEl);
            this.listDom.prepend(newEl);
            this.editor.setValue("");
            this.setActive(newEl);
            this.list = newEl;
        });

        //Store Data
        this.updateBtn.on('click', () => {
            this.setData();
        });
    }

    listEvent(list) {
        list.addEventListener('click', (e) => {
            this.loadData(list);
        });
        //if (list.querySelector('.removeList').length > 0) {
        list.querySelector('.removeList').addEventListener('click', () => {
            this.removeList(list);
        });
        // }
    }

    setActive(list) {
        this.listDom.find('li').removeClass('active');
        list.classList.add('active');
    }

    async loadData(list) {
        this.list = list;
        if (!this.list.classList.contains('newItem')) {
            jQuery(".keyword-keys").css('opacity', '.3');
            await jQuery.post(ajaxurl, { action: 'loadKeywords', name: list.getAttribute('data-name') }, (response) => {
                this.editor.setValue(response);
                this.setActive(this.list);
                jQuery(".keyword-keys").css('opacity', '1');
            });
        }
    }

    async setData() {
        let exHtm = this.updateBtn.html();
        this.updateBtn.html('...')
        await jQuery.post(ajaxurl, { action: 'StoreKeywords', name: this.list.getAttribute('data-name'), value: this.editor.getValue() }, (response) => {
            this.editor.setValue(response);
            this.updateBtn.html(exHtm);
            if (this.list.classList.contains('newItem')) {
                this.list.querySelector('span').removeAttribute('contenteditable');
                this.list.classList.remove('newItem');
            }
        });
    }

    async removeList(list) {
        if (confirm('Are you sure to delete ?')) {
            list.remove();
            this.editor.setValue("");
            await jQuery.post(ajaxurl, { action: 'removeList', name: list.getAttribute('data-name') }, (response) => {
                console.log(response);
            });
        }
    }
}

//Generating pages

let intval;
let generating = false;
let lIndex;
let PostID = false;
let limit;
let done = 0;
let stTime = new Date().getTime();

async function deleteStaticPages(id, _this) {
    jQuery(_this).html("<span class='dashicons dashicons-update loading'></span>");
    await jQuery.post(ajaxurl, { action: 'deleteStaticPages', id: id }, (response) => {
        response = JSON.parse(response);
        if (!response.error) {
            jQuery(_this).html("<span class='dashicons dashicons-saved'></span>");
            setTimeout(function () {
                jQuery(_this).html("<span class='dashicons dashicons-trash'></span>");
            }, 2000);
        } else {
            jQuery(_this).html("<span class='dashicons dashicons-saved'></span>");
        }
    });
}


async function GenerateStaticPage(id, _this) {
    jQuery(_this).html("<span class='dashicons dashicons-update loading'></span> Processing");
    limit = jQuery("#numberOfGenerate").val();
    await jQuery.post(ajaxurl, { action: 'generateStaticPage', id: id, limit: limit }, (response) => {
        response = JSON.parse(response);
        if (response.error) {
            console.log(response.msg);
        } else {
            PostID = response.id;
            jQuery('body').append(`
<div class="StaticGeneratingInfo">
    <div class='staticG-title'>
        <h3>Generating</h3>
        <span onclick="removeStaticGenerating()">&times;</span>
    </div>
    <div class="spg-progress">
        <div class="spg-progress-bar"></div>
    </div>
    <div class="countGenerate">Generated: <span class='gDone'>${done}</span> of <span class='gTotal'>${limit}</span></div>
    <div class="Generatingdetails"></div>
</div>`);
            jQuery(".spg-progress").addClass('visible');
            intval = setInterval(generateSingle, 100);
            jQuery(_this).html("<span class='dashicons dashicons-update loading'></span> Generating")
        }
    })
}



async function generateSingle() {
    stTime = new Date().getTime();
    //console.log(PostID, lIndex);
    if (!generating) {
        generating = true;
        await jQuery.post(ajaxurl, { action: 'generateStaticPageSingle', id: PostID, lIndex: lIndex }, (response) => {
            response = JSON.parse(response);
            if (!response.error) {
                done = (response.lIndex + 1);
                let prog = ((100 / limit) * done).toFixed(2);
                jQuery(".spg-progress-bar").css('width', prog + "%");
                jQuery(".gDone").html(done);
                generating = false;
                let newTime = new Date().getTime();
                let timeCons = newTime - stTime;
                stTime = newTime;

                response.links.forEach(lnk => {
                    jQuery(".Generatingdetails").prepend(`<div class='static-info-item'><label>link <a target='_blank' href='${lnk}'>${lnk}</a></label><label>Time Taken:${(timeCons / response.links.length).toFixed(2)}ms</label></div>`);
                });

                if (prog >= 100) {
                    clearInterval(intval);
                    jQuery("#generateBtn").html("Complete");
                } else {
                    lIndex = response.lIndex;
                }
            } else {
                console.log(response.msg);
            }
        });
    }
}


function removeStaticGenerating() {
    jQuery(".StaticGeneratingInfo").hide();
}

async function manualGenerate(id) {
    await jQuery.post(ajaxurl, { action: 'generateStaticPageSingle', id: id }, (response) => {
        response = JSON.parse(response);
    });
}

let manualIntVal;
let manualgenerating = false;
async function setStaticManualGenerateEvent() {
    if (!manualgenerating) {
        manualgenerating = true;
        await jQuery.post(ajaxurl, { action: 'setStaticManualGenerateEvent', }, (response) => {
            response = JSON.parse(response);
            response.forEach((el) => {
                let id = el.id;
                let progWrap = jQuery("#prog" + id);
                progWrap.find('.done-generate').html(el.done);
                let prog = (100 / el.total) * el.done;
                progWrap.find('.spg-progress-bar').css('width', prog + "%");
                manualgenerating = false;
            })
        });
    }

}

jQuery(document).ready(function () {
    if (jQuery(".listControllerWprogress").length > 0) {
        manualIntVal = setInterval(setStaticManualGenerateEvent, 2000);
    }
    //Manual Generate
    jQuery(".generateNow").on('click', async function (e) {
        let target = jQuery(e.target);
        target.html('<span class="dashicons dashicons-update loading"></span>');
        let wrp = target.closest('.listControllerWprogress');
        //console.log(wrp.find('.cronEnable').is(":checked"));
        wrp.find('.cronEnable').prop("checked", false);//$('#myCheckbox').prop('checked', true); // Checks it
        await jQuery.post(ajaxurl, { action: 'manualGenerateStatus', postID: wrp.attr('data-id') }, (response) => {
            if (response == '1') {
                target.html('Stop');
            } else {
                target.html('Manual Generate');
            }
        });
    })
    jQuery(".replaceGenerate").on('click', async function (e) {
        let target = jQuery(e.target);

        jQuery(target).find('span').addClass('loading');
        let wrp = target.closest('.listControllerWprogress');
        await jQuery.post(ajaxurl, { action: 'regenerate', id: wrp.attr('data-id') }, (response) => {
            //response = JSON.parse(response);
            wrp.find(".done-generate").html(0);
            wrp.find('.spg-progress-bar').css('width', '0%');
            jQuery(target).find('span').removeClass('loading');
        });
    });

    jQuery(".progDetails").click(() => {
        jQuery(".StaticGeneratingInfo").show();
    });

    jQuery(".key-tab").on('click', function (e) {
        e.preventDefault();
        jQuery('.key-tab').removeClass('nav-tab-active');
        jQuery(this).addClass('nav-tab-active');
        jQuery('.key-tab-item').removeClass('active');
        let terget = jQuery(this).attr('href');
        jQuery(terget).addClass('active');
    });

    jQuery(".update-csvData").click(() => {
        //console.log(loadedCsvFile, currentCsvData);
        updateCsvFile();
    });

    //Focus
    if (jQuery('.shortcodeToolbar').length > 0) {
        jQuery('input[type="text"],textarea').on('focus', (e) => {
            let tollbar = document.querySelector('.shortcodeToolbar');
            let target = e.target;
            activeInput = target;
            selStart = target.selectionStart;
            let inputBound = target.getBoundingClientRect();
            let tolB = tollbar.getBoundingClientRect();
            let topPos = (inputBound.top) - tolB.height;
            let leftPos = (inputBound.left + inputBound.width) - tolB.width;

            jQuery('.shortcodeToolbar').css({ 'left': leftPos, "top": topPos });
            //console.log(topPos, leftPos);
        });
    }
    //Remove Toolbar
    jQuery(".removeTool").click((e) => {
        jQuery(".shortcodeToolbar").css('top', '-100px');
    });
})

async function updateCsvFile() {
    let updBtn = jQuery(".update-csvData");
    let exHtm = updBtn.html();
    updBtn.html('...');

    let strCsv = '';

    currentCsvData.forEach((elm) => {
        elm = elm.filter(function (el) {
            return el != null;
        });
        if (elm.length > 0) {
            strCsv += '"' + elm.join('", "') + '"\n';
        }
    });
    await jQuery.post(ajaxurl, { action: 'updateCsvFile', name: loadedCsvFile, val: strCsv }, (response) => {
        response = JSON.parse(response);
        updBtn.html(exHtm);
        if (!response.error) {
            console.log('File Updated')
        }
    });
}


function uploadCsvFile2Server(_this) {
    let files = _this.files;
    let file = files[0];
    //console.log(file);
    const $ = jQuery;
    const data = new FormData();
    if (file.type !== 'application/vnd.ms-excel') {
        alert('File Type must be (*.csv)');
        return;
    }

    data.append('csvUpload', file); // append all files
    data.append('action', 'svgFile4keyworg'); //ajax Callback

    $.ajax({
        xhr: function () {
            var xhr = new window.XMLHttpRequest();
            xhr.upload.addEventListener("progress", function (evt) {
                if (evt.lengthComputable) {
                    var percentComplete = ((evt.loaded / evt.total) * 100);
                    $(".csv-progress").width(percentComplete + '%');
                }
            }, false);
            return xhr;
        },
        type: 'POST',
        url: ajaxurl,
        data: data,
        contentType: false,
        cache: false,
        processData: false,
        beforeSend: function () {
            $(".csv-progress").width('0%');
        },
        error: function () {
            //$('#uploadStatus').html('<p style="color:#EA4335;">File upload failed, please try again.</p>');
        },
        success: function (resp) {
            resp = JSON.parse(resp);
            console.log(resp.fname);
            if (!resp.error) {
                $(".svgFileList").append(`<li data-name='${resp.fname}' class='csvList' onclick='loadCsv(this)'><span class='removeList' onclick='removeCsv(this)'>&times;</span>${resp.fname}</li>`)
            } else {
                console.log('upload Error');
            }
        }
    });
}

//**
let loadedCsvFile = false;
let currentCsvData = [];
let activeInput = false;
let selStart = false;

function VisualizeCsv(data, quoteChar = '"', delimiter = ',') {
    var rows = data.split("\n");
    const regex = new RegExp(`\\s*(${quoteChar})?(.*?)\\1\\s*(?:${delimiter}|$)`, 'gs');
    const match = line => [...line.matchAll(regex)]
        .map(m => m[2])
        .slice(0, -1);
    //Dom=
    let table = document.createElement('table');
    if (Array.isArray(rows)) {
        let r = 0;
        let rs = [];
        rows.map(line => {

            let tr = document.createElement('tr');
            let c = 0;
            let cl = [];
            let cnt = document.createElement('td');
            cnt.innerHTML = `<span class="csv-row-count">${(r + 1)}</span>`;
            tr.appendChild(cnt);
            match(line).reduce((acc, cur, i) => {
                const val = cur.length <= 0 ? null : Number(cur) || cur;
                cl.push(val);
                let td = document.createElement('td');
                let dd = document.createElement('span');

                dd.innerHTML = val;
                dd.setAttribute('title', val);
                dd.classList.add('dataPlaceHolder');
                dd.setAttribute('data-address', r + ":" + c);
                dd.setAttribute('contenteditable', 'true');
                dd.addEventListener('keyup', (e) => {
                    jQuery('.update-csvData').css('display', 'flex');
                    let elm = e.target;
                    let modVal = e.target.innerHTML;
                    let address = elm.getAttribute('data-address');
                    let addresPart = address.split(":");
                    currentCsvData[addresPart[0]][addresPart[1]] = modVal;
                    //console.log(currentCsvData);//
                });
                td.appendChild(dd);
                tr.appendChild(td);
                c++;
            }, {});
            rs.push(cl);
            table.appendChild(tr);
            r++;
        });
        currentCsvData = rs;
    }
    document.querySelector('.svg-data').innerHTML = "";
    document.querySelector('.svg-data').appendChild(table);
}



async function loadCsv(_this) {
    //Remove Active class
    jQuery(".svg-data").html('<span class="loading-data">Loading Data...</span>');

    jQuery(".svgFileList li").removeClass('active');
    jQuery(".svg-data").css('opacity', '.3');
    //Set Active class
    jQuery(_this).addClass('active');
    //request for data   
    let filename = jQuery(_this).attr('data-name');
    await jQuery.post(ajaxurl, { action: 'loadCsv', name: filename }, (response) => {
        loadedCsvFile = filename;
        jQuery(".svg-data").css('opacity', '1');
        VisualizeCsv(response);
        //data Visualize
    });
}

async function removeCsv(_this) {
    let fileName = jQuery(_this).parent().attr('data-name');
    if (confirm('Are you sure to delete ?')) {
        await jQuery.post(ajaxurl, { action: 'removeCsv', name: fileName }, (response) => {
            console.log(response);
            jQuery(_this).parent().remove();
            jQuery(".svg-data").html("");
        });
    }
}

function insertCode(_this) {
    let a = jQuery(activeInput).val();
    var b = jQuery(_this).attr('data-code');
    var position = selStart;
    var output = [a.slice(0, position), b, a.slice(position)].join('');
    jQuery(activeInput).val(output);
}

async function changeStaticCronStatus(_this) {
    let id = jQuery(_this).attr('data-id');
    let ch = false;
    if (jQuery(_this).is(':checked')) {
        ch = true;
    }
    await jQuery.post(ajaxurl, { action: 'changeStaticCronStatus', postID: id, status: ch }, (response) => {
        console.log(response);
    });
}

// async function quickLinkGenerate(_this) {
//     jQuery(".generatedLinks").html("");
//     jQuery(_this).html('<span class="dashicons dashicons-update loading"></span>');
//     let inf = jQuery('#quickLinkInfo').val();
//     await jQuery.post(ajaxurl, { action: 'quickLinkGenerate', str: inf }, (response) => {
//         response = JSON.parse(response);
//         response.forEach((el) => {
//             jQuery(".generatedLinks").append(`<a href="${el}">${el}</a><br>`);
//             jQuery(_this).html('Generate');
//         });
//     });
// }
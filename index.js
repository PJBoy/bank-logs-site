let regex_longROMAddress2 = /([89A-D][0-9A-F]):([89A-F][0-9A-F]{3})/i;
let clickSource = null;

function insertAfter(parent, node, referenceNode)
{
    parent.insertBefore(node, referenceNode.nextSibling);
}

function escapeUrlArgument(arg)
{
    return encodeURIComponent(arg).replace(/%20/g, "+");
}

function sendAjaxGet(source, parameters, callback)
{
    let queryArguments = [];
    for (let k in parameters)
        queryArguments.push(`${k}=${escapeUrlArgument(parameters[k])}`);
    
    let queryString = queryArguments.join('&');
    let r = new XMLHttpRequest();
    if (callback)
        r.addEventListener('load', callback);
    
    r.open('GET', `${source}?${queryString}`);
    r.send();
}

function sample(span_address_rom)
{
    let romAddress = regex_longROMAddress2.exec(span_address_rom.textContent);
    let id = 'iframe_' + romAddress[1] + romAddress[2];
    let iframe_sample = document.getElementById(id);
    if (iframe_sample)
    {
        iframe_sample.remove();
        return;
    }
    
    iframe_sample = document.createElement('iframe');
    iframe_sample.id = id;
    iframe_sample.src = `${romAddress[1]}?just=${romAddress[2]}`;
    iframe_sample.width = '100%';
    iframe_sample.height = '400px';
    insertAfter(span_address_rom.parentNode, iframe_sample, span_address_rom);
}

function searchSample(bank, address)
{
    let iframe_searchSample = document.getElementById('search_sample');
    iframe_searchSample.src = `${bank}?just=${address}&highlight#${address}`;
    iframe_searchSample.width = '100%';
}

function getRam(span_address)
{
    if (span_address.children.length == 0)
        sendAjaxGet('ram.php', {'address': span_address.textContent.slice(1)}, function getRamCallback()
        {
            span_address.title = this.responseText;
            //span_address.innerHTML += `<span class="tooltip">${this.responseText}</span>`;
        });
}

function startSplitterDrag(event, el)
{
    clickSource = el;
    clickSource.setPointerCapture(event.pointerId);
    clickSource.addEventListener('pointermove', handleMouseMove);
    clickSource.addEventListener('lostpointercapture', handleMouseUp);

    return false;
}

function handleMouseMove()
{
    if (clickSource.id == "main_separator")
        document.getElementById("left").style.width = `${event.pageX}px`;
    else if (clickSource.id == "search_separator")
        document.getElementById("search_left").style.width = `${event.pageX}px`;
    else if (clickSource.id == "main_search_separator")
        document.getElementById("search_results_panel").style.height = `${document.body.clientHeight - event.pageY}px`;
}

function handleMouseUp()
{
    clickSource.removeEventListener('pointermove', handleMouseMove);
    clickSource.removeEventListener('lostpointercapture', handleMouseUp);
    clickSource = null;
}

function toggleDarkMode()
{
    if (document.documentElement.classList.contains("invertedColours"))
        document.documentElement.classList.remove("invertedColours");
    else
        document.documentElement.classList.add("invertedColours");
}

function clearSearch()
{
    let div_searchResultsPanel = document.getElementById("search_results_panel");
    let div_searchResults = document.getElementById("search_results");
    let div_searchSample = document.getElementById("search_sample");
    
    div_searchResults.innerHTML = "";
    div_searchResultsPanel.style.height = "0";
    div_searchSample.src = "";
}

function searchLogs(regex)
{
    // I need to show some kind of "in progress" sign
    sendAjaxGet('search.php', {'regex': regex}, function searchLogsCallback()
    {
        let div_searchResultsPanel = document.getElementById("search_results_panel");
        let div_searchResults = document.getElementById("search_results");
        let div_searchSample = document.getElementById("search_sample");
        
        div_searchResults.innerHTML = "";
        div_searchSample.src = "";
        
        let results = JSON.parse(this.responseText);
        let searchResultsText = "";
        for (let r of results)
        {
            let line = `<a title="Change page to result" href="${r.bank}#${r.address}">⛶</a> `;
            if (r.text.search(regex_longROMAddress2) != -1)
                line += r.text.replace(regex_longROMAddress2, `<a href="${r.bank}#${r.address}" onclick="searchSample('${r.bank}', '${r.address}'); return false;">${r.bank}:${r.address}</a>`);
            else
                line += `($<a href="${r.bank}#${r.address}" onclick="searchSample('${r.bank}', '${r.address}'); return false;">${r.bank}:${r.address}</a>)` + r.text;
            
            searchResultsText += `${line}\n`;
        }
        
        div_searchResults.innerHTML = searchResultsText;
        div_searchResultsPanel.style.height = "";
    });
}

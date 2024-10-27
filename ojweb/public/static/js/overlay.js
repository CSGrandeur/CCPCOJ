
function showOverlay(initialText = "Scanning ... Found 0 problem(s), 0 tests") {
    const overlay = document.createElement("div");
    overlay.id = "overlay";
    overlay.style.position = "fixed";
    overlay.style.top = "0";
    overlay.style.left = "0";
    overlay.style.width = "100%";
    overlay.style.height = "100%";
    overlay.style.backgroundColor = "rgba(0, 0, 0, 0.5)";
    overlay.style.color = "white";
    overlay.style.display = "flex";
    overlay.style.justifyContent = "center";
    overlay.style.alignItems = "center";
    overlay.style.zIndex = "10000";
    overlay.style.fontSize = "32px";
    overlay.innerHTML = `<div id="overlay-text" style="text-align:center;">${initialText}</div>`;
    document.body.appendChild(overlay);
    
}

function updateOverlay(initialText = "", ratio=null, additionText=null) {
    const overlayText = document.getElementById("overlay-text");
    if (overlayText) {
        let showText = initialText;
        if(ratio) {
            showText += `${ratio}%<br/>${showText}`
        }
        if(additionText) {
            showText += `<br/>${additionText}`;
        }
        overlayText.innerHTML = showText;
    }
}

function hideOverlay() {
    const overlay = document.getElementById("overlay");
    if (overlay) {
        document.body.removeChild(overlay);
    }
}

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
    overlay.innerHTML = `<div id="overlay-text">${initialText}</div>`;
    document.body.appendChild(overlay);
    
}

function updateOverlay(initialText = "") {
    const overlayText = document.getElementById("overlay-text");
    if (overlayText) {
        overlayText.innerHTML = initialText;
    }
}

function hideOverlay() {
    const overlay = document.getElementById("overlay");
    if (overlay) {
        document.body.removeChild(overlay);
    }
}
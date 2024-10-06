async function ChunkUpload(file, url, data = {}) {
    showOverlay(`Uploading ${file.name}... 0%`);

    // 添加短暂延迟以确保 overlay 能够及时渲染
    await new Promise(resolve => setTimeout(resolve, 100));

    const chunkSize = 1024 * 1024; // 每个分片的大小，1MB
    const totalChunks = Math.ceil(file.size / chunkSize);
    let uploadedChunks = 0;

    for (let i = 0; i < totalChunks; i++) {
        const start = i * chunkSize;
        const end = Math.min(file.size, start + chunkSize);
        const chunk = file.slice(start, end);

        const formData = new FormData();
        formData.append("upload_file", chunk);
        formData.append("index", i);
        formData.append("totalChunks", totalChunks);
        formData.append("fileName", file.name);

        // 添加附加参数到 FormData
        for (const key in data) {
            if (data.hasOwnProperty(key)) {
                formData.append(key, data[key]);
            }
        }

        try {
            const response = await fetch(url, {
                method: "POST",
                body: formData,
                headers: {
                    "x-requested-with": "XMLHttpRequest",
                },
            });

            const rep = await response.json();

            if (!response.ok || rep.code !== 1) {
                throw new Error(rep.msg || `Failed to upload chunk ${i}`);
            }

            uploadedChunks++;
            const progress = Math.round((uploadedChunks / totalChunks) * 100);

            updateOverlay(`Uploading ${file.name}... ${progress}%`);
        } catch (error) {
            console.error(error);
            alertify.alert(
                `Error uploading chunk ${i} of file ${file.name}: ${error.message}`
            );
            hideOverlay();
            return false;
        }
    }

    hideOverlay();
    return true;
}
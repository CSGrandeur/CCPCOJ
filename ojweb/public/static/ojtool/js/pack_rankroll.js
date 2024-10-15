async function GetResource(url, zipWriter, addedFiles, totalFiles, processedFiles) {
    // url: /static/...
    const path_name = (url.startsWith('/') ? url : new URL(url).pathname).replace(/^[\/.]+|[\/.]+$/g, '');
    const badgePath = `resource/${path_name}`;
    processedFiles.count++;
    const progress = ((processedFiles.count / totalFiles.count) * 100).toFixed(2);
    updateOverlay(`正在打包 ${badgePath}`, progress);
    try {
        const response = await fetch(url);
        if (response.ok) {
            const blob = await response.blob();
            if (!addedFiles.has(badgePath)) {
                await zipWriter.add(badgePath, new zip.BlobReader(blob));
                addedFiles.add(badgePath);
            } else {
                console.warn(`重复文件，跳过: ${badgePath}`);
            }
        }
    } catch (error) {
        console.warn(`Failed to fetch resource: ${url}`);
        console.error(error);
    }
}

async function PackPage(cdata, zipWriter, addedFiles, totalFiles, processedFiles) {
    // 获取当前页面的 HTML 内容
    const response = await fetch(window.location.href);
    let indexHtml = await response.text();

    // 解析 HTML 内容为 DOM 对象
    const parser = new DOMParser();
    const doc = parser.parseFromString(indexHtml, 'text/html');

    // 获取所有js、css资源
    const resources = Array.from(doc.querySelectorAll('script[src], link[rel="stylesheet"]')).map(el => el.src || el.href);

    // 更新总文件数
    totalFiles.count += resources.length + cdata.team.length * 2 + 3; // 资源文件数 + 队伍照片和徽章数 + 3个额外文件

    // 打包资源文件
    for (const resource of resources) {
        await GetResource(resource, zipWriter, addedFiles, totalFiles, processedFiles);
    }

    // 替换资源链接
    doc.querySelectorAll('script[src], link[rel="stylesheet"], link[rel="icon"]').forEach(el => {
        const url = new URL(el.src || el.href, window.location.href);
        const trimmedPathname = url.pathname.replace(/^\/+|\/+$/g, '').replace(/^\.+|\.+$/g, ''); // 去掉前后的斜杠和点号
        const path = `./resource/${trimmedPathname}`;
        if (el.tagName.toLowerCase() === 'script') {
            el.src = path;
        } else {
            el.href = path;
        }
    });

    // 去掉 header 和按钮
    const header = doc.querySelector('header');
    if (header) {
        header.remove();
    }
    doc.querySelectorAll('.button_init_data, #team_photo_btn, #pack_rankroll_btn').forEach(el => el.remove());

    // 移除对 pack_rankroll.js 的引入
    doc.querySelectorAll('script[src]').forEach(el => {
        if (el.src.includes('pack_rankroll.js')) {
            el.remove();
        }
    });
    doc.querySelectorAll('link[rel="stylesheet"]').forEach(el => {
        if (el.href.includes('sidebarlayout.css')) {
            el.remove();
        }
    });

    // 修改 FLG_PKG_MODE 变量
    const scriptTags = doc.querySelectorAll('script');
    scriptTags.forEach(script => {
        if (script.textContent.includes('var FLG_PKG_MODE = false;')) {
            script.textContent = script.textContent.replace('var FLG_PKG_MODE = false;', 'var FLG_PKG_MODE = true;');
        }
    });

    // 引入 data.js 并添加时间戳参数
    const body = doc.querySelector('body');
    const script = doc.createElement('script');
    script.type = 'text/javascript';
    script.src = `./data.js?t=${new Date().getTime()}`;
    body.appendChild(script);

    // 序列化 DOM 对象为 HTML 字符串
    const serializer = new XMLSerializer();
    const updatedHtml = serializer.serializeToString(doc);

    const indexBlob = new Blob([updatedHtml], {
        type: "text/html"
    });
    await zipWriter.add("index.html", new zip.BlobReader(indexBlob));
    processedFiles.count++;
    const progress = ((processedFiles.count / totalFiles.count) * 100).toFixed(2);
    updateOverlay(`正在打包 index.html`, progress);

    for (const team of cdata.team) {
        // 获取并打包队伍照片
        const teamPhotoUrl = `/upload/contest_attach/${contest_attach}/team_photo/${team.team_id}.jpg`;
        await GetResource(teamPhotoUrl, zipWriter, addedFiles, totalFiles, processedFiles);
        // 获取并打包学校徽章
        if (team.school) {
            const schoolBadgeUrl = `${URL_SCHOOL_BADGE_BASE}/${team.school}.jpg`;
            await GetResource(schoolBadgeUrl, zipWriter, addedFiles, totalFiles, processedFiles);
        }
    }
    // 处理动态加载的 bootstrap-icons
    const linkElement = document.querySelector('link[href*="bootstrap-icons"]');
    if (linkElement) {
        const iconPath = linkElement.href.replace(/\/font\/.*$/, '/font/fonts');
        console.log(12121, `${iconPath}/bootstrap-icons.woff2`);
        await GetResource(`${iconPath}/bootstrap-icons.woff2`, zipWriter, addedFiles, totalFiles, processedFiles);
        await GetResource(`${iconPath}/bootstrap-icons.woff`, zipWriter, addedFiles, totalFiles, processedFiles);
    } else {
        console.error('未找到包含 bootstrap-icons 的 <link> 标签');
    }
    await GetResource("/static/image/global/favicon.ico", zipWriter, addedFiles, totalFiles, processedFiles);
}

document.getElementById('pack_rankroll_btn').addEventListener('click', function () {
    alertify.confirm('确认打包？',
        `
        <strong>务必确认：</strong><br/>
        <span class="text-danger">1. 比赛是否已结束</span><br/>
        <span class="text-danger">2. <a href="/csgoj/contest/status?cid=${cid}" target="_blank">评测队列</a>是否已全部完成</span><br/>
        <span class="text-danger">3. <strong class="text-info">确认后，刷新本页</strong>确保获取最新数据，再导出最终结果</span><br/><br/>
        确认现在导出？
    `, async function () {
        showOverlay("正在打包...");

        const zipWriter = new zip.ZipWriter(new zip.BlobWriter("application/zip"));
        const addedFiles = new Set();
        const totalFiles = { count: 0 };
        const processedFiles = { count: 0 };

        const rep = await $.get('contest_data_ajax?cid=' + cid);
        if (rep.code != 1) {
            alertify.alert("请求数据失败");
            return;
        }
        const cdata = rep.data;

        // 保存 cdata 为 data.js
        const cdataJs = `var cdata = ${JSON.stringify(cdata)};`;
        const cdataBlob = new Blob([cdataJs], {
            type: "application/javascript"
        });
        await zipWriter.add("data.js", new zip.BlobReader(cdataBlob));
        processedFiles.count++;
        const progress = ((processedFiles.count / totalFiles.count) * 100).toFixed(2);
        updateOverlay(`正在打包 data.js`, progress);

        await PackPage(cdata, zipWriter, addedFiles, totalFiles, processedFiles);

        // 完成打包
        zipWriter.close().then(blob => {
            hideOverlay();
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `rankroll_${contest_title.replace(/[<>:"/\\|?*]+/g, '-')}.zip`;
            link.click();
        });
    }, function () { });
});
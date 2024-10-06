let map_contest_problem = new Map();
let list_other_problem = [];
let list_problem = [];
let polygon_pid = 1;

const SplitPath = (filename) => {
    return filename.split(/[/\\]/);
};

function showOverlay(initialText = "Scanning ... Find 0 problem(s), 0 tests") {
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

async function HandlePolygonZipFile(file) {
    // 显示 overlay
    showOverlay("Trying to find contest.xml");
    try {
        // 处理数据
        map_contest_problem = new Map();
        list_other_problem = [];
        list_problem = [];
        polygon_pid = 1;
        const contest_problem_name_list = await FindContestProblems(file);
        await FindProblemDirectories(file, contest_problem_name_list);

        // 将 map_contest_problem 的数据按 contest_problem_name_list 的顺序读出
        const ordered_contest_problems = contest_problem_name_list
            .map((name) => map_contest_problem.get(name))
            .filter((problem) => problem !== undefined);

        // 合并 ordered_contest_problems 和 list_other_problem
        list_problem = ordered_contest_problems.concat(list_other_problem);

        // 组织数据并更新表格
        const tableData = [];
        let idx = 1;
        for (const value of list_problem) {
            tableData.push({ idx: idx++, ...value });
        }
        // 隐藏 overlay
        hideOverlay();
        return tableData;
    } catch (error) {
        console.error("Error handling polygon zip file:", error);
    } finally {
        hideOverlay();
    }
}

async function FindContestProblems(zipFile) {
    // 查找 contest.xml 信息
    const zipReader = new zip.ZipReader(new zip.BlobReader(zipFile));
    const entries = await zipReader.getEntries();

    let contest_problem_name_list = [];

    for (const entry of entries) {
        if (entry.directory) {
            continue;
        }
        if (entry.filename.endsWith(".zip")) {
            const nestedZipBlob = await entry.getData(new zip.BlobWriter());
            const nestedNames = await FindContestProblems(nestedZipBlob);
            contest_problem_name_list.push(...nestedNames);
        } else if (entry.filename === "contest.xml") {
            const text = await entry.getData(new zip.TextWriter());
            const parser = new DOMParser();
            const xmlDoc = parser.parseFromString(text, "application/xml");

            const problems = xmlDoc.getElementsByTagName("problem");
            for (let problem of problems) {
                const url = problem.getAttribute("url");
                const lastSegment = url.substring(url.lastIndexOf("/") + 1);
                contest_problem_name_list.push(lastSegment);
            }
        }
    }

    await zipReader.close();
    return contest_problem_name_list;
}

async function FindProblemDirectories(zipFile, contest_problem_name_list) {
    // 查找题目信息，优先按 contest.xml 信息排序，否则按发现顺序
    const zipReader = new zip.ZipReader(new zip.BlobReader(zipFile));
    const entries = await zipReader.getEntries();

    for (const entry of entries) {
        if (entry.directory) {
            continue;
        }

        if (entry.filename.endsWith(".zip")) {
            const nestedZipBlob = await entry.getData(new zip.BlobWriter());
            await FindProblemDirectories(nestedZipBlob, contest_problem_name_list);
        } else if (entry.filename.endsWith("problem.xml")) {
            const parts = SplitPath(entry.filename);
            const dirPath =
                parts.length > 1
                    ? entry.filename.substring(0, entry.filename.lastIndexOf("/") + 1)
                    : "";
            const problem_entries =
                dirPath == ""
                    ? entries
                    : entries.filter((e) => e.filename.startsWith(dirPath));
            if (parts.length > 1) {
                const dirName = parts[parts.length - 2];
                if (contest_problem_name_list.includes(dirName)) {
                    map_contest_problem.set(
                        dirName,
                        await FetchProblem(problem_entries, entry)
                    );
                } else {
                    list_other_problem.push(await FetchProblem(problem_entries, entry));
                }
            } else {
                list_other_problem.push(await FetchProblem(problem_entries, entry));
            }
        }
    }

    await zipReader.close();
}

async function AttachHash(projson) {
    const now = new Date();
    const dateStr = now.toISOString().slice(0, 10);
    const hashed_str = await crypto.subtle.digest(
        "SHA-256",
        new TextEncoder().encode(`${projson?.authorName}-${projson?.legend}`)
    );
    const hashed_res = Array.from(new Uint8Array(hashed_str))
        .map((b) => b.toString(16).padStart(2, "0"))
        .join("")
        .toUpperCase()
        .slice(0, 16);
    return `${dateStr}_${hashed_res}`;
}

async function MakeProblemJson(entry, pid) {
    try {
        const text = await entry.getData(new zip.TextWriter());
        const projson = JSON.parse(text);
        const now = new Date();
        return {
            accepted: 0,
            attach: await AttachHash(projson),
            author: "",
            author_md: projson?.authorName || projson?.authorLogin,
            defunct: "0",
            description: "",
            description_md: `__LATEX__\n\n${projson?.legend || "-"}`,
            hint: "",
            hint_md: `__LATEX__\n\n${projson?.notes || ""}`,
            in_date: now.toISOString().slice(0, 19).replace("T", " "),
            input: "",
            input_md: `__LATEX__\n\n${projson?.input || ""}`,
            output: "",
            output_md: `__LATEX__\n\n${projson?.output || ""}`,
            time_limit: ((projson?.timeLimit || 1000) / 1000).toFixed(1),
            memory_limit: Math.round(
                (projson?.memoryLimit || 268435456) / 1024 / 1024
            ),
            problem_id: 0,
            problem_new_id: pid,
            sample_input: projson.sampleTests
                .map((test) => test.input.replace(/\r\n/g, "\n"))
                .join("\n##CASE##\n"),
            sample_output: projson.sampleTests
                .map((test) => test.output.replace(/\r\n/g, "\n"))
                .join("\n##CASE##\n"),
            solved: 0,
            source: "",
            source_md: "",
            spj: "0",
            submit: 0,
            title: projson?.name || "NO TITLE",
        };
    } catch (error) {
        alertify.error(`Read file ${entry.filename} failed: ${error}`);
        console.error("Error reading or parsing file:", error);
        throw error;
    }
}

async function MakeTestData(
    proble_title,
    problem_entries,
    problem_xml_entry,
    includeSpj
) {
    const zipWriter = new zip.ZipWriter(new zip.BlobWriter("application/zip"));

    const problemDir = problem_xml_entry.filename.substring(
        0,
        problem_xml_entry.filename.lastIndexOf("/")
    );
    const testsDir = problem_entries.filter((entry) => {
        if (problemDir == "") {
            return (
                entry.filename.startsWith(`tests/`) ||
                entry.filename.startsWith(`/tests/`)
            );
        }
        return entry.filename.startsWith(`${problemDir}/tests/`);
    });
    let fileCount = 0;
    let totalSize = 0; // 累计文件大小
    for (const entry of testsDir) {
        if (entry.filename.endsWith(".a")) {
            const baseFilename = entry.filename.substring(
                0,
                entry.filename.length - 2
            );
            const inFile = problem_entries.find((e) => e.filename === baseFilename);

            if (inFile) {
                const outFileContent = await entry.getData(new zip.BlobWriter());
                const inFileContent = await inFile.getData(new zip.BlobWriter());

                // 计算文件大小
                totalSize += outFileContent.size + inFileContent.size;

                // 将文件放在压缩包的根目录
                const outFilename = `${baseFilename.substring(
                    baseFilename.lastIndexOf("/") + 1
                )}.out`;
                const inFilename = `${baseFilename.substring(
                    baseFilename.lastIndexOf("/") + 1
                )}.in`;

                await zipWriter.add(outFilename, new zip.BlobReader(outFileContent));
                await zipWriter.add(inFilename, new zip.BlobReader(inFileContent));
                fileCount++;
            }
        }
    }
    if (includeSpj) {
        const spjEntry = problem_entries.find((entry) =>
            entry.filename.endsWith("check.cpp")
        );
        if (spjEntry) {
            const spjContent = await spjEntry.getData(new zip.BlobWriter());
            await zipWriter.add("tpj.cc", new zip.BlobReader(spjContent));
            totalSize += spjContent.size; // 计算SPJ文件大小
        } else {
            alertify.warn(`problem ${proble_title} does not have check.cpp`);
        }
    }

    const zipContent = await zipWriter.close();
    return { zipContent, fileCount, totalSize, problem_xml_entry, entries: problem_entries };
}

async function FetchProblem(problem_entries, problem_xml_entry) {
    let statement_entry = null,
        statement_lang = null;
    for (const entry of problem_entries) {
        if (entry.filename.endsWith("problem-properties.json")) {
            if (entry.filename.includes("/statements/chinese/")) {
                statement_entry = entry;
                statement_lang = "cn";
            } else if (
                entry.filename.includes("/statements/english/") &&
                statement_lang != "cn"
            ) {
                statement_entry = entry;
                statement_lang = "en";
            } else if (!statement_entry) {
                statement_entry = entry;
            }
        }
    }
    let pid = polygon_pid++;
    const problemJson = await MakeProblemJson(statement_entry, pid);
    const testData = await MakeTestData(
        problemJson.title,
        problem_entries,
        problem_xml_entry,
        true
    );

    // 更新 overlay 信息
    const overlayText = document.getElementById("overlay-text");
    if (overlayText) {
        const problemCount = map_contest_problem.size + list_other_problem.length;
        const testCount =
            list_other_problem.reduce(
                (acc, problem) => acc + problem.testData.fileCount,
                0
            ) + map_contest_problem.size;
        updateOverlay(
            `Scanning ... Find ${problemCount} problem(s), ${testCount} tests`
        );
    }

    let totalSizeMB = testData.totalSize / (1024 * 1024);
    if (totalSizeMB < 0.01) {
        totalSizeMB = totalSizeMB.toFixed(4);
    } else if (totalSizeMB < 0.1) {
        totalSizeMB = totalSizeMB.toFixed(3);
    } else {
        totalSizeMB = totalSizeMB.toFixed(2);
    }

    return {
        idx: pid,
        title: `<a href="#" onclick="downloadJson(${pid})">${problemJson.title}</a>`,
        author: problemJson.author_md,
        testdata: `<a href="#" onclick="handleDownloadTestData(${pid})">${testData.fileCount} tests, ${totalSizeMB} MB</a>`,
        spj: `<input type="checkbox" class="spj-switch" data-pid="${pid}" checked>`,
        hash: problemJson.attach,
        problemJson,
        testData,
    };
}

function GetSpjSwitch(pid) {
    return document.querySelector(`.spj-switch[data-pid="${pid}"]`);
}
function downloadJson(pid) {
    const problem =
        map_contest_problem.get(pid) ||
        list_other_problem.find((p) => p.idx === pid);
    if (problem) {
        const spjCheckbox = GetSpjSwitch(pid);
        let res_filename = `${pid}.json`;
        if (spjCheckbox && spjCheckbox.checked) {
            problem.problemJson.spj = "1";
            res_filename = `${pid}_with_tpj.json`;
        } else {
            problem.problemJson.spj = "0";
        }
        const formattedJson = JSON.stringify(problem.problemJson, null, 4); // 格式化 JSON
        const blob = new Blob([formattedJson], { type: "application/json" });
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = res_filename;
        a.click();
        URL.revokeObjectURL(url);
    }
}

async function downloadTestData(pid) {
    const problem =
        map_contest_problem.get(pid) ||
        list_other_problem.find((p) => p.idx === pid);
    if (problem) {
        const spjCheckbox = GetSpjSwitch(pid);
        const includeSpj = spjCheckbox && spjCheckbox.checked;
        try {
            const testData = await MakeTestData(
                problem.problemJson.title,
                problem.testData.entries,
                problem.testData.problem_xml_entry,
                includeSpj
            );
            if (testData && testData.zipContent) {
                const url = URL.createObjectURL(testData.zipContent);
                const a = document.createElement("a");
                a.href = url;
                a.download = `${pid}${includeSpj ? '_with_tpj' : ''}.zip`;
                a.click();
                URL.revokeObjectURL(url);
            } else {
                console.error("testData.zipContent is undefined");
            }
        } catch (error) {
            console.error("Error creating test data:", error);
        }
    }
}

function handleDownloadTestData(pid) {
    downloadTestData(pid).catch((error) => {
        console.error("Error downloading test data:", error);
    });
}
async function downloadSelectedProblems() {
    showOverlay("Packing ... 0 problem(s) packed");
    try {
        const selectedProblems = $("#polygon_parse_table").bootstrapTable(
            "getSelections"
        );
        if (selectedProblems.length === 0) {
            alertify.error("At least one problem should be selected.");
            return;
        }

        const zipWriter = new zip.ZipWriter(new zip.BlobWriter("application/zip"));
        const problemList = [];

        for (let i = 0; i < selectedProblems.length; i++) {
            const problem = selectedProblems[i];
            const spjCheckbox = GetSpjSwitch(pid);
            if (spjCheckbox && spjCheckbox.checked) {
                problem.problemJson.spj = "1";
            } else {
                problem.problemJson.spj = "0";
            }
            console.log(problem.problemJson)
            problemList.push(problem.problemJson);

            const testDir = `TEST_${String(i + 1).padStart(5, "0")}`;
            const testDataEntries = await problem.testData.zipContent.arrayBuffer();
            const testZipReader = new zip.ZipReader(
                new zip.BlobReader(new Blob([testDataEntries]))
            );

            const entries = await testZipReader.getEntries();
            for (const entry of entries) {
                const data = await entry.getData(new zip.BlobWriter());
                await zipWriter.add(
                    `${testDir}/${entry.filename}`,
                    new zip.BlobReader(data)
                );
            }

            if (problem.problemJson.spj === "1") {
                const spjEntry = problem.testData.entries.find((entry) =>
                    entry.filename.endsWith("check.cpp")
                );
                if (spjEntry) {
                    const spjContent = await spjEntry.getData(new zip.BlobWriter());
                    await zipWriter.add(
                        `${testDir}/tpj.cc`,
                        new zip.BlobReader(spjContent)
                    );
                }
            }

            updateOverlay(`Packing ... ${i + 1} problem(s) packed`);
        }

        await zipWriter.add(
            "problemlist.json",
            new zip.TextReader(JSON.stringify(problemList, null, 4))
        );
        const zipContent = await zipWriter.close();

        const url = URL.createObjectURL(zipContent);
        const a = document.createElement("a");
        a.href = url;
        a.download = "selected_problems.zip";
        a.click();
        URL.revokeObjectURL(url);
        hideOverlay();
    } catch (error) {
        console.error("Error downloading selected problems:", error);
    } finally {
        hideOverlay();
    }
}

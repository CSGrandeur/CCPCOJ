let map_contest_problem = new Map();
let list_other_problem = [];
let list_problem = [];
let polygon_pid = 1;

const SplitPath = (filename) => {
    return filename.split(/[/\\]/);
};


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
        for(let i = 0; i < list_problem.length; i ++) {
            const pid = i + 1;
            list_problem[i].idx         =    pid;
            list_problem[i].title       =   `<a href="#" onclick="DownloadPro(${pid})">${list_problem[i].title}</a>`;
            list_problem[i].testdata    =   `<a href="#" onclick="handleDownloadTestData(${pid})">${list_problem[i].testdata}</a>`;
            list_problem[i].spj         =   `<input type="checkbox" class="spj-switch" data-pid="${pid}" checked>`;
        }
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
    const hashed_res = FNV1aHash2Str(`${projson?.authorName}-${projson?.legend}`, 16);
    return `${dateStr}_${hashed_res}`;
}
async function MakeProblemJson(entry, pid) {
    try {
        const text = await entry.getData(new zip.TextWriter());
        const projson = JSON.parse(text);
        const now = new Date();
        const attach_hash = await AttachHash(projson);

        const attach_files = [];
        const prefix = `/upload/problem_attach/${attach_hash}/`;

        const replaceGraphicsPath = (content) => {
            return content.replace(/\\includegraphics(?:\[[^\]]*\])?\{([^}]+)\}/g, (match, p1) => {
                if (!p1.startsWith("http")) {
                    // 如果路径是局部路径“./xxx”的形式，则去掉路径，仅保留文件名
                    const trimmedPath = p1.replace(/^(\.\/|\\)+|(\.\/|\\)+$/g, '');
                    // 如果路径包含目录，则不执行替换，也不保存
                    if (!trimmedPath.includes("/") && !trimmedPath.includes("\\")) {
                        attach_files.push(trimmedPath);
                        return match.replace(p1, `${prefix}${trimmedPath}`);
                    }
                }
                return match;
            });
        };

        projson.legend = replaceGraphicsPath(projson.legend || "");
        projson.notes = replaceGraphicsPath(projson.notes || "");
        projson.input = replaceGraphicsPath(projson.input || "");
        projson.output = replaceGraphicsPath(projson.output || "");

        return {
            problem: {
                accepted:       0,
                attach:         attach_hash,
                author:         "",
                author_md:      projson?.authorName || projson?.authorLogin,
                defunct:        "0",
                description:    "",
                description_md: `__LATEX__\n\n${projson?.legend || "-"}`,
                hint:           "",
                hint_md:        `__LATEX__\n\n${projson?.notes || ""}`,
                in_date:        now.toISOString().slice(0, 19).replace("T", " "),
                input:          "",
                input_md:       `__LATEX__\n\n${projson?.input || ""}`,
                output:         "",
                output_md:      `__LATEX__\n\n${projson?.output || ""}`,
                time_limit:     ((projson?.timeLimit || 1000) / 1000).toFixed(1),
                memory_limit:   Math.round((projson?.memoryLimit || 268435456) / 1024 / 1024),
                problem_id:     0,
                problem_new_id: pid,
                sample_input:   projson.sampleTests
                                    .map((test) => test.input.replace(/\r\n/g, "\n"))
                                    .join("\n##CASE##\n"),
                sample_output:  projson.sampleTests
                                    .map((test) => test.output.replace(/\r\n/g, "\n"))
                                    .join("\n##CASE##\n"),
                solved:         0,
                source:         "",
                source_md:      "",
                spj:            "0",
                submit:         0,
                title:          projson?.name || "NO TITLE"
            },
            attach_files: attach_files
        };
    } catch (error) {
        alertify.error(`Read file ${entry.filename} failed: ${error}`);
        console.error("Error reading or parsing file:", error);
        throw error;
    }
}

async function MakeAttachFiles(problem_entries, statement_entry, attach_files) {
    const statementDir = statement_entry.filename.substring(0, statement_entry.filename.lastIndexOf("/") + 1);
    const attachFilesData = [];

    for (const file of attach_files) {
        const filePath = statementDir + file;
        const entry = problem_entries.find((e) => e.filename === filePath);
        if (entry) {
            const fileContent = await entry.getData(new zip.BlobWriter());
            attachFilesData.push({ filename: file, content: fileContent });
        }
    }

    return attachFilesData;
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
    let statement_entry = null, statement_lang = null;
    for (const entry of problem_entries) {
        if (entry.filename.endsWith("problem-properties.json")) {
            if (entry.filename.includes("statements/chinese/")) {
                statement_entry = entry;
                statement_lang = "chinese";
            } else if (
                entry.filename.includes("statements/english/") &&
                statement_lang != "english"
            ) {
                statement_entry = entry;
                statement_lang = "english";
            } else if (!statement_entry) {
                statement_entry = entry;
                let file_patch = SplitPath(entry.filename);
                statement_lang = file_patch[file_patch.length - 2];
            }
        }
    }
    let pid = polygon_pid++;
    const { problem, attach_files } = await MakeProblemJson(statement_entry, pid);
    const testData = await MakeTestData(
        problem.title,
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
            `Scanning ... Found ${problemCount} problem(s), ${testCount} tests`
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
        title:          problem.title,
        author:         problem.author_md,
        testdata:       `${testData.fileCount} tests, ${totalSizeMB} MB`,
        spj:            '',
        hash:           problem.attach,
        problemJson:    problem,
        testData,
        statement_entry,
        attach_files
    };
}

function GetSpjSwitch(pid) {
    return document.querySelector(`.spj-switch[data-pid="${pid}"]`);
}
function DownloadPro(pid) {
    const problem = list_problem.find((p) => p.idx === pid);
    if (problem) {
        const spjCheckbox = GetSpjSwitch(pid);
        if (spjCheckbox && spjCheckbox.checked) {
            problem.problemJson.spj = "1";
        } else {
            problem.problemJson.spj = "0";
        }
        problem.problemJson.problem_new_id = 1;

        const zipWriter = new zip.ZipWriter(new zip.BlobWriter("application/zip"));
        zipWriter.add("problemlist.json", new zip.TextReader(JSON.stringify([problem.problemJson], null, 4)));

        if (problem.attach_files && problem.attach_files.length > 0) {
            MakeAttachFiles(problem.testData.entries, problem.statement_entry, problem.attach_files).then((attachFilesData) => {
                attachFilesData.forEach(async (file) => {
                    await zipWriter.add(`ATTACH_${String(1).padStart(5, "0")}/${file.filename}`, new zip.BlobReader(file.content));
                });
                zipWriter.close().then((zipContent) => {
                    const url = URL.createObjectURL(zipContent);
                    const a = document.createElement("a");
                    a.href = url;
                    a.download = `${pid}.zip`;
                    a.click();
                    URL.revokeObjectURL(url);
                });
            });
        } else {
            zipWriter.close().then((zipContent) => {
                const url = URL.createObjectURL(zipContent);
                const a = document.createElement("a");
                a.href = url;
                a.download = `${pid}.zip`;
                a.click();
                URL.revokeObjectURL(url);
            });
        }
    }
}

async function DownloadTestData(pid) {
    const problem = list_problem.find((p) => p.idx === pid);
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
    DownloadTestData(pid).catch((error) => {
        console.error("Error downloading test data:", error);
    });
}
async function DownloadSelectedProblems() {
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
            const spjCheckbox = GetSpjSwitch(problem.idx);
            if (spjCheckbox && spjCheckbox.checked) {
                problem.problemJson.spj = "1";
            } else {
                problem.problemJson.spj = "0";
            }
            problem.problemJson.problem_new_id = i + 1;
            problemList.push(problem.problemJson);

            const testDir = `TEST_${String(i + 1).padStart(5, "0")}`;
            const testDataEntries = await problem.testData.zipContent.arrayBuffer();
            const testZipReader = new zip.ZipReader(
                new zip.BlobReader(new Blob([testDataEntries]))
            );

            const entries = await testZipReader.getEntries();
            const addedFiles = new Set(); // 用于跟踪已添加的文件

            for (const entry of entries) {
                const data = await entry.getData(new zip.BlobWriter());
                const filePath = `${testDir}/${entry.filename}`;
                if (!addedFiles.has(filePath)) {
                    await zipWriter.add(filePath, new zip.BlobReader(data));
                    addedFiles.add(filePath);
                }
            }

            if (problem.problemJson.spj === "1") {
                const spjEntry = problem.testData.entries.find((entry) =>
                    entry.filename.endsWith("check.cpp")
                );
                if (spjEntry) {
                    const spjContent = await spjEntry.getData(new zip.BlobWriter());
                    const spjFilePath = `${testDir}/tpj.cc`;
                    if (!addedFiles.has(spjFilePath)) {
                        await zipWriter.add(spjFilePath, new zip.BlobReader(spjContent));
                        addedFiles.add(spjFilePath);
                    }
                }
            }

            // 添加 ATTACH_${i + 1} 目录（如果有附件文件）
            if (problem.attach_files && problem.attach_files.length > 0) {
                const attachDir = `ATTACH_${String(i + 1).padStart(5, "0")}`;
                const attachFilesData = await MakeAttachFiles(problem.testData.entries, problem.statement_entry, problem.attach_files);
                for (const file of attachFilesData) {
                    await zipWriter.add(`${attachDir}/${file.filename}`, new zip.BlobReader(file.content));
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
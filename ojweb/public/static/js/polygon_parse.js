let map_contest_problem = new Map();
let list_other_problem = [];
let list_problem = [];
let polygon_pid = 1;
var scanning_info = "Scanning problem ...";

const SplitPath = (filename) => {
    return filename.split(/[/\\]/);
};


async function HandlePolygonZipFile(file) {
    // 显示 overlay
    showOverlay('');
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
        // 组织数据并更新表格，保持纯数据，不包含 HTML 标签，formatter 会在表格渲染时处理
        const tableData = [];
        for (let i = 0; i < list_problem.length; i++) {
            const pid = i + 1;
            // 确保每个问题都有 idx，用于 formatter 中的引用
            tableData.push({ 
                idx: pid,
                ...list_problem[i]
            });
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
    const overlay_main_info = "Trying to find contest.xml";
    // 查找 contest.xml 信息
    const zipReader = new zip.ZipReader(new zip.BlobReader(zipFile));
    const entries = await zipReader.getEntries();

    let contest_problem_name_list = [];

    for (const entry of entries) {
        if (entry.directory) {
            continue;
        }
        updateOverlay(overlay_main_info, null, entry.filename);
        if (entry.filename.endsWith(".zip")) {
            const nestedZipBlob = await entry.getData(new zip.BlobWriter());
            const nestedNames = await FindContestProblems(nestedZipBlob);
            contest_problem_name_list.push(...nestedNames);
        } else if (IsFile(entry.filename, "contest.xml")) {
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
function IsFile(fullPath, filename) {
    const regex = new RegExp(`(^|[\\/])${filename}$`);
    return regex.test(fullPath);
}
async function FindProblemDirectories(zipFile, contest_problem_name_list) {
    const overlay_main_info = "Trying to find problem directories";
    // 查找题目信息，优先按 contest.xml 信息排序，否则按发现顺序
    const zipReader = new zip.ZipReader(new zip.BlobReader(zipFile));
    const entries = await zipReader.getEntries();

    for (const entry of entries) {
        if (entry.directory) {
            continue;
        }
        updateOverlay(overlay_main_info, null, entry.filename);
        if (entry.filename.endsWith(".zip")) {
            const nestedZipBlob = await entry.getData(new zip.BlobWriter());
            await FindProblemDirectories(nestedZipBlob, contest_problem_name_list);
        } else if (IsFile(entry.filename, "problem.xml")) {
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
                sample_input:   JSON.stringify({
                    data_type: 'json',
                    data: projson.sampleTests.map((test) => test.input.replace(/\r\n/g, "\n"))
                }),
                sample_output:  JSON.stringify({
                    data_type: 'json',
                    data: projson.sampleTests.map((test) => test.output.replace(/\r\n/g, "\n"))
                }),
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
        alerty.error(
            `读取文件 ${entry.filename} 失败：${error}`,
            `Failed to read file ${entry.filename}: ${error}`
        );
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
function GetDirPath(filename) {
    return filename.substring(0, filename.lastIndexOf("/"));
}
function FindChecker(entries, problemXmlDir) {
    return entries.find((entry) => 
        problemXmlDir == ""  ? entry.filename == "check.cpp" : entry.filename == `${problemXmlDir}/check.cpp`
    );
}

function FindInteractor(entries, problemXmlDir) {
    const interactorPath = problemXmlDir == "" ? "files/interactor.cpp" : `${problemXmlDir}/files/interactor.cpp`;
    return entries.find((entry) => {
        return entry.filename === interactorPath || entry.filename.endsWith("/files/interactor.cpp");
    });
} 
async function MakeTestData(
    proble_title,
    problem_entries,
    problem_xml_entry,
    spjType,
    tip_info="Processing"
) {
    const zipWriter = new zip.ZipWriter(new zip.BlobWriter("application/zip"));

    const problemDir = GetDirPath(problem_xml_entry.filename);
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
        updateOverlay(scanning_info, null, `${tip_info} test data ...<br/>${entry.filename}`);
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
    
    // 根据 spj 类型添加对应的文件
    if (spjType === "1") {
        // 特判评测：使用 check.cpp
        const spjEntry = FindChecker(problem_entries, problemDir);
        if (spjEntry) {
            const spjContent = await spjEntry.getData(new zip.BlobWriter());
            await zipWriter.add("tpj.cc", new zip.BlobReader(spjContent));
            totalSize += spjContent.size; // 计算SPJ文件大小
        } else {
            alerty.warn(
                `题目 ${proble_title} 不存在 check.cpp`,
                `Problem ${proble_title} does not have check.cpp`
            );
        }
    } else if (spjType === "2") {
        // 交互评测：使用 files/interactor.cpp
        const interactorEntry = FindInteractor(problem_entries, problemDir);
        if (interactorEntry) {
            const interactorContent = await interactorEntry.getData(new zip.BlobWriter());
            await zipWriter.add("tpj.cc", new zip.BlobReader(interactorContent));
            totalSize += interactorContent.size; // 计算交互文件大小
        } else {
            alerty.warn(
                `题目 ${proble_title} 不存在 files/interactor.cpp`,
                `Problem ${proble_title} does not have files/interactor.cpp`
            );
        }
    }
    // spjType === "0" 时不添加任何文件（标准评测）

    const zipContent = await zipWriter.close();
    return { zipContent, fileCount, totalSize, problem_xml_entry, entries: problem_entries };
}
async function FetchProblem(problem_entries, problem_xml_entry) {
    let statement_entry = null, statement_lang = null;
    for (const entry of problem_entries) {
        if (IsFile(entry.filename, "problem-properties.json")) {
            if (entry.filename.includes("statements/chinese/")) {
                statement_entry = entry;
                statement_lang = "chinese";
            } else if (
                entry.filename.includes("statements/english/") &&
                statement_lang != "chinese"
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
    
    // 检测交互题：如果存在 files/interactor.cpp，则认为是交互题 (spj=2)
    const problemDir = GetDirPath(problem_xml_entry.filename);
    const interactorEntry = FindInteractor(problem_entries, problemDir);
    const checkerEntry = FindChecker(problem_entries, problemDir);
    
    let spjType = "0"; // 默认标准评测
    if (interactorEntry) {
        // 存在 interactor.cpp，确定为交互题
        spjType = "2";
        problem.spj = "2";
    } else if (checkerEntry) {
        // 存在 check.cpp，确定为特判题
        spjType = "1";
        problem.spj = "1";
    } else {
        // 都不存在，标准评测
        spjType = "0";
        problem.spj = "0";
    }
    
    const testData = await MakeTestData(
        problem.title,
        problem_entries,
        problem_xml_entry,
        spjType
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
        scanning_info = `Scanning ... Found ${problemCount} problem(s), ${testCount} tests`;
        updateOverlay(scanning_info);
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
        spj:            spjType, // 保存 spj 类型：0=标准, 1=特判, 2=交互
        spjType:        spjType, // 用于 formatter 判断显示类型
        hash:           problem.attach,
        problemJson:    problem,
        testData,
        statement_entry,
        attach_files
    };
}

function GetSpjSwitch(pid) {
    return document.querySelector(`.csg-switch-input[data-pid="${pid}"]`);
}
function DownloadPro(pid) {
    const problem = list_problem.find((p) => p.idx === pid);
    if (problem) {
        // 交互题和标准评测不需要用户选择，直接使用检测到的类型
        // 只有特判题需要根据开关状态决定是否包含 check.cpp
        if (problem.spjType === "1") {
            // 特判题：根据开关状态决定
            const spjCheckbox = GetSpjSwitch(pid);
            if (spjCheckbox && spjCheckbox.checked) {
                problem.problemJson.spj = "1";
            } else {
                problem.problemJson.spj = "0";
            }
        } else {
            // 交互题或标准评测：使用检测到的类型
            problem.problemJson.spj = problem.spjType;
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
        let spjType = problem.spjType;
        // 只有特判题需要根据开关状态决定
        if (spjType === "1") {
            const spjCheckbox = GetSpjSwitch(pid);
            if (spjCheckbox && spjCheckbox.checked) {
                spjType = "1";
            } else {
                spjType = "0"; // 不包含特判文件
            }
        }
        try {
            const testData = await MakeTestData(
                problem.problemJson.title,
                problem.testData.entries,
                problem.testData.problem_xml_entry,
                spjType,
                "Packing"
            );
            if (testData && testData.zipContent) {
                const url = URL.createObjectURL(testData.zipContent);
                const a = document.createElement("a");
                let suffix = '';
                if (spjType === "1") {
                    suffix = '_with_tpj';
                } else if (spjType === "2") {
                    suffix = '_interactive';
                }
                a.download = `${pid}${suffix}.zip`;
                a.href = url;
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
    scanning_info = "";
    showOverlay();
    DownloadTestData(pid).catch((error) => {
        console.error("Error downloading test data:", error);
    }).finally(()=> hideOverlay());
}
async function DownloadSelectedProblems() {
    let packing_info = "Packing ...";
    showOverlay(packing_info);
    try {
        const selectedProblems = $("#polygon_parse_table").bootstrapTable(
            "getSelections"
        );
        if (selectedProblems.length === 0) {
            alerty.error(
                "至少需要选择一个题目",
                "At least one problem should be selected"
            );
            return;
        }

        const zipWriter = new zip.ZipWriter(new zip.BlobWriter("application/zip"));
        const problemList = [];

        for (let i = 0; i < selectedProblems.length; i++) {
            const problem = selectedProblems[i];
            let finalSpjType = problem.spjType;
            
            // 只有特判题需要根据开关状态决定
            if (problem.spjType === "1") {
                const spjCheckbox = GetSpjSwitch(problem.idx);
                if (spjCheckbox && spjCheckbox.checked) {
                    finalSpjType = "1";
                } else {
                    finalSpjType = "0";
                }
            }
            
            problem.problemJson.spj = finalSpjType;
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
                updateOverlay(packing_info, null, `Packing test data<br/>${entry.filename}`);
                const data = await entry.getData(new zip.BlobWriter());
                const filePath = `${testDir}/${entry.filename}`;
                if (!addedFiles.has(filePath)) {
                    await zipWriter.add(filePath, new zip.BlobReader(data));
                    addedFiles.add(filePath);
                }
            }

            // 根据最终类型添加对应的文件
            const problemDir = GetDirPath(problem.testData.problem_xml_entry.filename);
            if (finalSpjType === "1") {
                // 特判评测：使用 check.cpp
                const spjEntry = FindChecker(problem.testData.entries, problemDir);
                if (spjEntry) {
                    const spjContent = await spjEntry.getData(new zip.BlobWriter());
                    const spjFilePath = `${testDir}/tpj.cc`;
                    if (!addedFiles.has(spjFilePath)) {
                        await zipWriter.add(spjFilePath, new zip.BlobReader(spjContent));
                        addedFiles.add(spjFilePath);
                    }
                }
            } else if (finalSpjType === "2") {
                // 交互评测：使用 files/interactor.cpp
                const interactorEntry = FindInteractor(problem.testData.entries, problemDir);
                if (interactorEntry) {
                    const interactorContent = await interactorEntry.getData(new zip.BlobWriter());
                    const interactorFilePath = `${testDir}/tpj.cc`;
                    if (!addedFiles.has(interactorFilePath)) {
                        await zipWriter.add(interactorFilePath, new zip.BlobReader(interactorContent));
                        addedFiles.add(interactorFilePath);
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
            packing_info = `Packing ... ${i + 1} problem(s) packed`;
            updateOverlay(packing_info);
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


// ========================================
// Polygon Parser 表格 Formatter 函数
// ========================================

// 索引 formatter
function FormatterProParserIdx(value, row, index, field) {
    return value || (index + 1);
}

// 标题 formatter - 带下载链接
function FormatterProParserTitle(value, row, index, field) {
    if (!value) return '-';
    const pid = row.idx || (index + 1);
    return `<a href="#" onclick="DownloadPro(${pid}); return false;" class="text-decoration-none text-primary" title="下载题目 (Download Problem)">${value}</a>`;
}

// 作者 formatter
function FormatterProParserAuthor(value, row, index, field) {
    return value || '-';
}

// 测试数据 formatter - 带下载链接
function FormatterProParserTestData(value, row, index, field) {
    if (!value) return '-';
    const pid = row.idx || (index + 1);
    return `<a href="#" onclick="handleDownloadTestData(${pid}); return false;" class="text-decoration-none text-success" title="下载测试数据 (Download Test Data)">${value}</a>`;
}

// 特判 formatter - csg-switch 开关
function FormatterProParserSpj(value, row, index, field) {
    const pid = row.idx || (index + 1);
    // 初始化时默认选中（checked），因为 Polygon 默认有 check.cpp
    const isChecked = (row.spj_checked !== false && value !== '0'); // 默认选中，除非明确设置为 false 或 '0'
    return `<div class="csg-switch">
        <input type="checkbox" class="csg-switch-input" data-pid="${pid}" ${isChecked ? 'checked' : ''}>
    </div>`;
}

// 哈希 formatter
function FormatterProParserHash(value, row, index, field) {
    if (!value) return '-';
    // 哈希值可以用代码格式显示
    return `<code style="font-size: 0.85em;">${value}</code>`;
}
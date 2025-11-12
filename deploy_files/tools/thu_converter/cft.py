"""
将清华酒井算协出ACM题目的文件包转为CSGOJ导入格式
pip install pyyaml
"""
import os
import shutil
import yaml
import json
import re
import hashlib
import time
from datetime import datetime
import zipfile
from tqdm import tqdm


SOURCE = ""
PATH_IN = "./pro_from"
PATH_OUT = "output"

log_rec = []

def parse_md(md_content, attach_folder=None):

    md_content = re.sub(r"\{\{\s*img\('([^']*)',.*?\}\}", rf"![](/upload/problem_attach/{attach_folder}/\1)", md_content)

    description_start = md_content.find('{{ s(\'description\') }}')
    input_pre = md_content.find("{{ s('input format') }}")
    input_start = md_content.find('{{ self.input_file() }}')
    output_pre = md_content.find("{{ s('output format') }}")
    output_start = md_content.find('{{ self.output_file() }}')
    output_end = md_content.find("{{ s('sample',")
    hint_start = md_content.find('{{ self.title_sample_description() }}')

    description_md = md_content[description_start:input_pre].replace('{{ s(\'description\') }}', '').strip()
    input_md = md_content[input_start:output_pre].replace('{{ self.input_file() }}', '').strip()
    output_md = md_content[output_start:output_end].replace('{{ self.output_file() }}', '').strip()
    hint_md = md_content[hint_start:].replace('{{ self.title_sample_description() }}', '').strip()

    return description_md, input_md, output_md, hint_md


def ProcessOnePro(pro, ith):
    print(f"正在处理: {pro}")
    path_pro = os.path.join(PATH_IN, pro)
    # 读取 conf.yaml 文件
    with open(os.path.join(path_pro, 'conf.yaml'), 'r', encoding='utf-8') as f:
        conf = yaml.safe_load(f)


    attach = datetime.now().strftime('%Y-%m-%d') + '_' + hashlib.md5(f"{pro}_{str(time.time)}".encode()).hexdigest().upper()[:16]

    # 读取 statement/zh-cn.md 文件并解析
    file_md = 'zh-cn.md' if os.path.exists(os.path.join(path_pro, 'statement', 'zh-cn.md')) else 'en.md'
    with open(os.path.join(path_pro, 'statement', file_md), 'r', encoding='utf-8') as f:
        md_content = f.read()
    description_md, input_md, output_md, hint_md = parse_md(md_content, attach)

    # 读取 sample
    sample_input = []
    sample_output = []
    for cs in conf['samples'][0]['cases']:
        with open(os.path.join(path_pro, 'down', f'{cs}.in'), 'r', encoding='utf-8') as f:
            s_in = f.read()
        with open(os.path.join(path_pro, 'down', f'{cs}.ans'), 'r', encoding='utf-8') as f:
            s_out = f.read()
        sample_input.append(s_in)
        sample_output.append(s_out)
    sample_input = '\n##CASE##\n'.join(sample_input)
    sample_output = '\n##CASE##\n'.join(sample_output)


    # 构造 JSON 对象
    problem_json = {
        "accepted": 0,
        "attach": attach,
        "author": "",
        "author_md": "",
        "defunct": "1",
        "description": "",
        "description_md": description_md.encode('unicode_escape').decode(),
        "hint": "",
        "hint_md": hint_md.encode('unicode_escape').decode(),
        "in_date": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        "input": "",
        "input_md": input_md.encode('unicode_escape').decode(),
        "memory_limit": int(conf['memory limit'].split()[0]),
        "output": "",
        "output_md": output_md.encode('unicode_escape').decode(),
        "problem_id": 0,
        "problem_new_id": ith,
        "sample_input": sample_input,
        "sample_output": sample_output,
        "solved": 0,
        "source": "",
        "source_md": SOURCE,
        "spj": "0",
        "submit": 0,
        "time_limit": float(conf['time limit']),
        "title": conf['title']['zh-cn']
    }

    # 处理附件
    path_resource_from = os.path.join(PATH_IN, pro, 'resources')
    if os.path.isdir(path_resource_from):
        path_resource_to = os.path.join(PATH_OUT, f'ATTACH_{ith:05d}')
        os.makedirs(path_resource_to)
        for filename in os.listdir(path_resource_from):
            src_file = os.path.join(path_resource_from, filename)
            dst_file = os.path.join(path_resource_to, filename)
            if os.path.isfile(src_file):
                shutil.copy2(src_file, dst_file)

    # 处理评测数据
    path_data_from = os.path.join(PATH_IN, pro, 'data')
    path_data_to = os.path.join(PATH_OUT, f'TEST_{ith:05d}')
    os.makedirs(path_data_to)
    for cs in conf['data'][0]['cases']:
        path_d_in_from = os.path.join(path_data_from, f"{cs}.in")
        path_d_out_from = os.path.join(path_data_from, f"{cs}.ans")
        path_d_in_to = os.path.join(path_data_to, f"{cs}.in")
        path_d_out_to = os.path.join(path_data_to, f"{cs}.out")
        if os.path.exists(path_d_in_from) and os.path.exists(path_d_out_from):
            shutil.copy(path_d_in_from, path_d_in_to)
            shutil.copy(path_d_out_from, path_d_out_to)
        else:
            info = f"[warning]: 检查 {path_d_in_from} 或 {path_d_out_from} 是否存在"
            log_rec.append(info)
            print(info)

    for file in os.listdir(path_data_from):
        if "chk" in file:
            info = f"[warning]: <{pro}-{conf['title']['zh-cn']}>存在special judge，注意手动处理"
            log_rec.append(info)
            print(info)
            break
    return problem_json

def zipdir(path, ziph):
    all_files = []
    for root, dirs, files in os.walk(path):
        for file in files:
            all_files.append(os.path.join(root, file))
    
    with tqdm(total=len(all_files), desc="打包中...", ncols=100) as pbar:
        for file in all_files:
            relpath = os.path.relpath(file, path)
            ziph.write(file, relpath)
            pbar.update(1)
            
if __name__ == '__main__':

    pro_list = os.listdir(PATH_IN)
    pro_list.sort()
    i = 0
    if os.path.exists(PATH_OUT):
        shutil.rmtree(PATH_OUT)
    os.mkdir(PATH_OUT)
    pro_info_list = []
    for pro in pro_list:
        if pro.isalpha() and pro.isupper() and len(pro) == 1:
            i += 1
            problem_json = ProcessOnePro(pro, i)
            pro_info_list.append(problem_json)
    with open(os.path.join(PATH_OUT, "problemlist.json"), "w") as f:
        problem_json_str = json.dumps(pro_info_list, ensure_ascii=False, indent=4).replace("\\\\", "\\")
        f.write(problem_json_str)
        
    i=10
    zipf = zipfile.ZipFile(f"pro_total_{i}_{datetime.now().strftime('%Y-%m-%d')}.zip", 'w', zipfile.ZIP_DEFLATED)
    zipdir(PATH_OUT, zipf)
    zipf.close()

    log_rec.append(f"完成，共{i}题")
    print(f"完成，共{i}题")

    with open('log.txt', 'w') as f:
        f.write("\n".join(log_rec))

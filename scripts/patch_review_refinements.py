with open('local_testcase_exchange/templates/review.mustache', 'r', encoding='utf-8') as f:
    content = f.read()

target1 = """                        <div>
                            <span class="text-muted small">Phân loại:</span>
                            <span class="badge badge-light border text-dark ml-1">{{category}}</span>
                        </div>"""

replacement1 = """                        <div>
                            <span class="text-muted small">{{#str}} category, local_testcase_exchange {{/str}}:</span>
                            <span class="badge badge-light border text-dark ml-1">{{category_label}}</span>
                        </div>"""

assert target1 in content, "target1 not found in review.mustache"
content = content.replace(target1, replacement1, 1)

target2 = """                <div class="row mb-3">
                    <div class="col-md-4 mb-2 mb-md-0">
                        <div class="small font-weight-bold text-dark mb-1">
                            <i class="fa fa-terminal text-muted mr-1"></i>Dữ liệu đầu vào (Input):
                        </div>
                        <pre class="font-monospace bg-light p-2 border rounded text-dark mb-0" style="max-height: 140px; overflow-y: auto; white-space: pre-wrap; word-break: break-all;">{{input_normalized}}</pre>
                    </div>
                    <div class="col-md-4 mb-2 mb-md-0">
                        <div class="small font-weight-bold text-dark mb-1">
                            <i class="fa fa-bullseye text-muted mr-1"></i>Expected Output (Sinh viên):
                        </div>
                        <pre class="font-monospace bg-light p-2 border rounded text-dark mb-0" style="max-height: 140px; overflow-y: auto; white-space: pre-wrap; word-break: break-all;">{{expected_output_display}}</pre>
                    </div>
                    <div class="col-md-4">
                        <div class="small font-weight-bold text-dark mb-1">
                            <i class="fa fa-check text-muted mr-1"></i>Lời giải tham chiếu (Oracle):
                        </div>
                        <pre class="font-monospace bg-light p-2 border rounded text-dark mb-0" style="max-height: 140px; overflow-y: auto; white-space: pre-wrap; word-break: break-all;">{{oracle_output}}</pre>
                    </div>
                </div>"""

replacement2 = """                <div class="row mb-3">
                    <div class="col-md-4 mb-2 mb-md-0">
                        <div class="small font-weight-bold text-dark mb-1">
                            <i class="fa fa-terminal text-muted mr-1"></i>{{#str}} input_data_label, local_testcase_exchange {{/str}}:
                        </div>
                        <pre class="font-monospace bg-light p-2 border rounded text-dark mb-0" style="max-height: 140px; overflow-y: auto; white-space: pre-wrap; word-break: break-all;">{{input_normalized}}</pre>
                    </div>
                    <div class="col-md-4 mb-2 mb-md-0">
                        <div class="small font-weight-bold text-dark mb-1">
                            <i class="fa fa-bullseye text-muted mr-1"></i>{{#str}} student_expected_output_label, local_testcase_exchange {{/str}}:
                        </div>
                        <pre class="font-monospace bg-light p-2 border rounded text-dark mb-0" style="max-height: 140px; overflow-y: auto; white-space: pre-wrap; word-break: break-all;">{{expected_output_display}}</pre>
                    </div>
                    <div class="col-md-4">
                        <div class="small font-weight-bold text-dark mb-1">
                            <i class="fa fa-check text-muted mr-1"></i>{{#str}} reference_solution_output_label, local_testcase_exchange {{/str}}:
                        </div>
                        <pre class="font-monospace bg-light p-2 border rounded text-dark mb-0" style="max-height: 140px; overflow-y: auto; white-space: pre-wrap; word-break: break-all;">{{oracle_output}}</pre>
                    </div>
                </div>"""

assert target2 in content, "target2 not found in review.mustache"
content = content.replace(target2, replacement2, 1)

with open('local_testcase_exchange/templates/review.mustache', 'w', encoding='utf-8') as f:
    f.write(content)

print("Updated review.mustache successfully")

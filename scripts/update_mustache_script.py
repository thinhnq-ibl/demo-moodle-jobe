import os

path = os.path.join("local_testcase_exchange", "templates", "dashboard.mustache")
with open(path, "r", encoding="utf-8") as f:
    text = f.read()

marker = "{{! 3. CLIENT-SIDE PERSISTENCE & INTERACTIVITY"
idx = text.find(marker)
if idx == -1:
    print("Marker not found!")
    exit(1)

before = text[:idx]

new_block = """{{! 3. CLIENT-SIDE PERSISTENCE & INTERACTIVITY                                }}
{{! ========================================================================= }}
<script>
(function() {
    var courseId = '{{course_id}}';
    var questionVal = '{{selected_question_value}}';
    var userId = '{{sesskey}}';

    var codeEl = document.getElementById('student_code');
    var inputEl = document.getElementById('test_input');
    var predictedEl = document.getElementById('predicted_output');
    var purposeEl = document.getElementById('purpose');
    var categoryEl = document.getElementById('category');
    var statusBadge = document.getElementById('storageStatusBadge');
    var questionSelect = document.getElementById('question');
    var btnSubmitRun = document.getElementById('btnSubmitRunTest');

    function getStorageKey() {
        return 'tce_draft_' + courseId + '_' + questionVal;
    }

    function updateStatus(msg, isSuccess) {
        if (!statusBadge) return;
        statusBadge.textContent = msg;
        statusBadge.className = 'small ' + (isSuccess ? 'text-success font-weight-bold' : 'text-muted font-italic');
    }

    function sanitizeMultiline(val) {
        if (!val) return '';
        return val.replace(/\\r\\n/g, '\\n').replace(/\\r/g, '\\n');
    }

    function syncAceToCodeEl() {
        if (window.ace && codeEl) {
            var aceEditorDiv = document.getElementById('student_code_ace');
            if (aceEditorDiv && window.ace.edit) {
                try {
                    var ed = window.ace.edit(aceEditorDiv);
                    if (ed) {
                        codeEl.value = ed.getValue();
                    }
                } catch (e) {}
            }
        }
    }

    function syncCodeElToAce() {
        if (window.ace && codeEl) {
            var aceEditorDiv = document.getElementById('student_code_ace');
            if (aceEditorDiv && window.ace.edit) {
                try {
                    var ed = window.ace.edit(aceEditorDiv);
                    if (ed && ed.getValue() !== codeEl.value) {
                        ed.setValue(codeEl.value, -1);
                        ed.clearSelection();
                    }
                } catch (e) {}
            }
        }
    }

    function saveDraft() {
        syncAceToCodeEl();
        var key = getStorageKey();
        var data = {
            code: codeEl ? sanitizeMultiline(codeEl.value) : '',
            input: inputEl ? sanitizeMultiline(inputEl.value) : '',
            predicted: predictedEl ? sanitizeMultiline(predictedEl.value) : '',
            category: categoryEl ? categoryEl.value : 'normal',
            purpose: purposeEl ? purposeEl.value : '',
            updated_at: new Date().toLocaleTimeString()
        };
        try {
            localStorage.setItem(key, JSON.stringify(data));
            updateStatus('Đã lưu nháp tự động trên trình duyệt lúc ' + data.updated_at, true);
        } catch (e) {
            console.warn('LocalStorage error', e);
        }
    }

    function restoreDraft() {
        if (codeEl) codeEl.value = sanitizeMultiline(codeEl.value);
        if (inputEl) inputEl.value = sanitizeMultiline(inputEl.value);
        if (predictedEl) predictedEl.value = sanitizeMultiline(predictedEl.value);

        var key = getStorageKey();
        try {
            var raw = localStorage.getItem(key);
            if (!raw) {
                syncCodeElToAce();
                return;
            }
            var data = JSON.parse(raw);
            if (!data) {
                syncCodeElToAce();
                return;
            }

            var restored = false;
            var savedCode = sanitizeMultiline(data.code);
            var savedInput = sanitizeMultiline(data.input);
            var savedPredicted = sanitizeMultiline(data.predicted);

            if (codeEl && (!codeEl.value.trim() || codeEl.value.indexOf('\\n') !== -1) && savedCode && savedCode.trim()) {
                codeEl.value = savedCode;
                restored = true;
            }
            if (inputEl && (!inputEl.value.trim() || inputEl.value.indexOf('\\n') !== -1) && savedInput && savedInput.trim()) {
                inputEl.value = savedInput;
                restored = true;
            }
            if (predictedEl && (!predictedEl.value.trim() || predictedEl.value.indexOf('\\n') !== -1) && savedPredicted && savedPredicted.trim()) {
                predictedEl.value = savedPredicted;
                restored = true;
            }
            if (purposeEl && !purposeEl.value.trim() && data.purpose) {
                purposeEl.value = data.purpose;
            }
            if (categoryEl && data.category && (!categoryEl.value || categoryEl.value === 'normal')) {
                categoryEl.value = data.category;
            }

            syncCodeElToAce();
            saveDraft();
            if (restored) {
                updateStatus('Đã khôi phục bản nháp từ trình duyệt (' + (data.updated_at || 'trước đó') + ')', true);
            }
        } catch (e) {
            console.warn('LocalStorage error', e);
        }
    }

    var saveTimer = null;
    function queueSave() {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(saveDraft, 400);
    }

    if (codeEl) codeEl.addEventListener('input', queueSave);
    if (inputEl) inputEl.addEventListener('input', queueSave);
    if (predictedEl) predictedEl.addEventListener('input', queueSave);
    if (purposeEl) purposeEl.addEventListener('input', queueSave);
    if (categoryEl) categoryEl.addEventListener('change', queueSave);

    var runForm = document.getElementById('runTestForm');
    if (runForm) {
        runForm.addEventListener('submit', function() {
            syncAceToCodeEl();
            saveDraft();
            sessionStorage.setItem('tce_just_ran', '1');
            if (btnSubmitRun) {
                btnSubmitRun.disabled = true;
                btnSubmitRun.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Đang chạy...';
            }
        });
    }

    if (questionSelect) {
        questionSelect.addEventListener('change', function() {
            saveDraft();
            var newQ = this.value;
            if (newQ) {
                var url = new URL(window.location.href);
                url.searchParams.set('question', newQ);
                window.location.href = url.toString();
            }
        });
    }

    // Revisions restore button with flash highlight
    document.querySelectorAll('.btn-apply-revision').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var container = this.closest('.list-group-item') || this.parentElement;
            var ta = container.querySelector('.revision-full-code');
            if (ta && codeEl) {
                codeEl.value = sanitizeMultiline(ta.value);
                syncCodeElToAce();
                saveDraft();
                codeEl.classList.remove('tce-flash-active');
                void codeEl.offsetWidth; // Force reflow
                codeEl.classList.add('tce-flash-active');
                codeEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                codeEl.focus();
                updateStatus('Đã nạp phiên bản vào vùng soạn thảo.', true);
            }
        });
    });

    // Run code load button
    document.querySelectorAll('.btn-load-run-code').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var row = this.closest('tr') || this.closest('.tce-mobile-run-card');
            var ta = row ? row.querySelector('.run-code-content') : null;
            if (ta && codeEl) {
                codeEl.value = sanitizeMultiline(ta.value);
                syncCodeElToAce();
                saveDraft();
                codeEl.classList.remove('tce-flash-active');
                void codeEl.offsetWidth;
                codeEl.classList.add('tce-flash-active');
                codeEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                codeEl.focus();
                updateStatus('Đã nạp mã nguồn từ lượt chạy vào vùng soạn thảo.', true);
            }
        });
    });

    // Clear testcase inputs button (does not touch source code)
    var btnClearTest = document.getElementById('btnClearTestInputs');
    if (btnClearTest) {
        btnClearTest.addEventListener('click', function() {
            if (inputEl) inputEl.value = '';
            if (predictedEl) predictedEl.value = '';
            if (purposeEl) purposeEl.value = '';
            saveDraft();
            if (inputEl) inputEl.focus();
            updateStatus('Đã xóa dữ liệu testcase để nhập mới.', false);
        });
    }

    // Toggle older runs function on mobile
    window.toggleOlderRuns = function() {
        var olderRuns = document.querySelectorAll('.tce-older-run');
        var btn = document.getElementById('btnToggleOlderRuns');
        var isHidden = true;
        olderRuns.forEach(function(el) {
            if (el.classList.contains('d-none')) {
                el.classList.remove('d-none');
                isHidden = false;
            } else {
                el.classList.add('d-none');
                isHidden = true;
            }
        });
        if (btn) {
            btn.innerHTML = isHidden ?
                '<i class="fa fa-chevron-down mr-1"></i>Xem thêm (' + olderRuns.length + ' lượt chạy cũ hơn)' :
                '<i class="fa fa-chevron-up mr-1"></i>Thu gọn lượt chạy cũ';
        }
    };

    // Question prompt toggle text update
    if (window.jQuery) {
        window.jQuery('#questionPromptBody').on('show.bs.collapse', function() {
            var lbl = document.getElementById('lblToggleQuestionPrompt');
            if (lbl) lbl.textContent = 'Thu gọn';
        }).on('hide.bs.collapse', function() {
            var lbl = document.getElementById('lblToggleQuestionPrompt');
            if (lbl) lbl.textContent = 'Mở rộng';
        });
    }

    // Smooth scroll to latest result card if user just ran a test
    var latestResult = document.getElementById('tceLatestResultCard');
    if (latestResult && sessionStorage.getItem('tce_just_ran') === '1') {
        sessionStorage.removeItem('tce_just_ran');
        latestResult.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    restoreDraft();
})();
</script>

{{#js}}
require(['local_testcase_exchange/editor'], function(editor) {
    editor.init({
        textareaId: 'student_code',
        wrapperId: 'tceEditorWrapper',
        editorDivId: 'student_code_ace',
        coderunnerType: '{{coderunnertype}}',
        readOnly: false
    });
});
{{/js}}

</div>
<!-- end .local-testcase-exchange-root -->
"""

with open(path, "w", encoding="utf-8") as f:
    f.write(before + new_block)

print("Updated dashboard.mustache successfully!")

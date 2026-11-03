with open('local_testcase_exchange/templates/dashboard.mustache', 'r', encoding='utf-8') as f:
    content = f.read()

target = """    function saveDraft() {
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
    }"""

replacement = """    function getStr(key, fallback) {
        if (window.M && window.M.util && typeof window.M.util.get_string === 'function') {
            try {
                var res = window.M.util.get_string(key, 'local_testcase_exchange');
                if (res && res.indexOf('[[') === -1) {
                    return res;
                }
            } catch (e) {}
        }
        return fallback;
    }

    function formatTime(d) {
        var h = String(d.getHours()).padStart(2, '0');
        var m = String(d.getMinutes()).padStart(2, '0');
        var s = String(d.getSeconds()).padStart(2, '0');
        return h + ':' + m + ':' + s;
    }

    function saveDraft() {
        syncAceToCodeEl();
        var key = getStorageKey();
        var now = new Date();
        var data = {
            code: codeEl ? sanitizeMultiline(codeEl.value) : '',
            input: inputEl ? sanitizeMultiline(inputEl.value) : '',
            predicted: predictedEl ? sanitizeMultiline(predictedEl.value) : '',
            category: categoryEl ? categoryEl.value : 'normal',
            purpose: purposeEl ? purposeEl.value : '',
            updated_at: formatTime(now),
            timestamp: now.getTime()
        };
        try {
            localStorage.setItem(key, JSON.stringify(data));
            var msg = getStr('draft_saved_device_justnow', 'Đã lưu nháp tự động trên thiết bị này (vừa xong)');
            updateStatus(msg, true);
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
                var tm = data.updated_at || getStr('draft_earlier', 'trước đó');
                var restoredMsg = getStr('draft_restored_device', 'Đã khôi phục bản nháp từ thiết bị này ({$a})').replace('{$a}', tm);
                updateStatus(restoredMsg, true);
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
                btnSubmitRun.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> ' + getStr('running', 'Đang chạy...');
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
                updateStatus(getStr('revision_loaded', 'Đã nạp phiên bản vào vùng soạn thảo.'), true);
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
                updateStatus(getStr('run_code_loaded', 'Đã nạp mã nguồn từ lượt chạy vào vùng soạn thảo.'), true);
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
            updateStatus(getStr('testcase_cleared', 'Đã xóa dữ liệu testcase để nhập mới.'), false);
        });
    }

    // Toggle older runs function for both desktop table and mobile cards
    window.toggleOlderRuns = function() {
        var olderRuns = document.querySelectorAll('.tce-older-run');
        var btns = document.querySelectorAll('.btn-toggle-older-runs, #btnToggleOlderRuns');
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
        var count = olderRuns.length;
        if (document.querySelector('.tce-history-desktop-table') && document.querySelector('.tce-mobile-run-card')) {
            count = Math.round(count / 2);
        }
        var showMoreText = getStr('show_more_runs', 'Xem thêm ({$a} lượt chạy cũ hơn)').replace('{$a}', count);
        var hideText = getStr('hide_older_runs', 'Thu gọn lượt chạy cũ');
        btns.forEach(function(btn) {
            btn.innerHTML = isHidden ?
                '<i class="fa fa-chevron-down mr-1"></i>' + showMoreText :
                '<i class="fa fa-chevron-up mr-1"></i>' + hideText;
        });
    };

    // Auto-expand older runs if navigating directly to a hidden run via anchor
    function checkHashTarget() {
        if (window.location.hash && window.location.hash.indexOf('#run-') === 0) {
            var target = document.querySelector(window.location.hash);
            if (target && target.classList.contains('tce-older-run')) {
                var olderRuns = document.querySelectorAll('.tce-older-run');
                olderRuns.forEach(function(el) { el.classList.remove('d-none'); });
                var btns = document.querySelectorAll('.btn-toggle-older-runs, #btnToggleOlderRuns');
                var hideText = getStr('hide_older_runs', 'Thu gọn lượt chạy cũ');
                btns.forEach(function(btn) {
                    btn.innerHTML = '<i class="fa fa-chevron-up mr-1"></i>' + hideText;
                });
            }
        }
    }
    window.addEventListener('hashchange', checkHashTarget);
    setTimeout(checkHashTarget, 200);

    // Modal dialog: Return focus to trigger button upon close
    if (window.jQuery) {
        window.jQuery('.modal').on('hidden.bs.modal', function() {
            var id = this.id;
            var trigger = document.querySelector('[data-target="#' + id + '"]');
            if (trigger) {
                trigger.focus();
            }
        });
    }

    // Question prompt toggle text update
    if (window.jQuery) {
        window.jQuery('#questionPromptBody').on('show.bs.collapse', function() {
            var lbl = document.getElementById('lblToggleQuestionPrompt');
            if (lbl) lbl.textContent = getStr('collapse', 'Thu gọn');
        }).on('hide.bs.collapse', function() {
            var lbl = document.getElementById('lblToggleQuestionPrompt');
            if (lbl) lbl.textContent = getStr('expand', 'Mở rộng');
        });
    }"""

assert target in content, "target not found"
content = content.replace(target, replacement, 1)

with open('local_testcase_exchange/templates/dashboard.mustache', 'w', encoding='utf-8') as f:
    f.write(content)

print("Updated dashboard.mustache inline script successfully")

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Ace Editor wrapper module for Moodle Testcase Exchange.
 * Provides offline syntax-highlighted code editing, bi-directional sync,
 * theme switching, fullscreen, and accessibility controls.
 *
 * @module     local_testcase_exchange/editor
 * @copyright  2026 Nguyen Quoc Thinh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/str'], function($, str) {
    'use strict';

    var STORAGE_THEME_KEY = 'tce_editor_theme';
    var THEME_LIGHT = 'ace/theme/chrome';
    var THEME_DARK = 'ace/theme/tomorrow_night';

    /**
     * Map CodeRunner question type string to Ace syntax mode and human label.
     *
     * @param {string} coderunnerType CodeRunner question type.
     * @return {object} {mode: string, label: string}
     */
    function getAceModeForCoderunner(coderunnerType) {
        if (!coderunnerType || typeof coderunnerType !== 'string') {
            return { mode: 'ace/mode/text', label: 'Plain Text' };
        }
        var t = coderunnerType.toLowerCase().trim();
        if (t.indexOf('python') !== -1) {
            return { mode: 'ace/mode/python', label: 'Python 3' };
        }
        if (t.indexOf('java') !== -1) {
            return { mode: 'ace/mode/java', label: 'Java' };
        }
        if (t === 'cpp' || t.indexOf('c++') !== -1 || t.indexOf('cpp') !== -1) {
            return { mode: 'ace/mode/c_cpp', label: 'C++' };
        }
        if (t === 'c' || t.indexOf('c_') === 0 || t.indexOf('c-') === 0 || t === 'c_program' || t === 'c_function') {
            return { mode: 'ace/mode/c_cpp', label: 'C' };
        }
        if (t.indexOf('js') !== -1 || t.indexOf('javascript') !== -1 || t.indexOf('node') !== -1) {
            return { mode: 'ace/mode/javascript', label: 'JavaScript' };
        }
        if (t.indexOf('php') !== -1) {
            return { mode: 'ace/mode/php', label: 'PHP' };
        }
        if (t.indexOf('pascal') !== -1) {
            return { mode: 'ace/mode/pascal', label: 'Pascal' };
        }
        return { mode: 'ace/mode/text', label: coderunnerType };
    }

    /**
     * Dynamically load offline Ace script bundle if window.ace is not yet present.
     *
     * @param {function} callback Called when ace is ready or failed.
     */
    function ensureAceLoaded(callback) {
        if (typeof window.ace !== 'undefined' && window.ace.edit) {
            callback(true);
            return;
        }

        var baseUrl = M.cfg.wwwroot + '/local/testcase_exchange/ace/';
        var script = document.createElement('script');
        script.type = 'text/javascript';
        script.src = baseUrl + 'ace.js';
        script.onload = function() {
            if (typeof window.ace !== 'undefined') {
                window.ace.config.set('basePath', baseUrl);
                window.ace.config.set('modePath', baseUrl);
                window.ace.config.set('themePath', baseUrl);
                window.ace.config.set('workerPath', baseUrl);
                callback(true);
            } else {
                callback(false);
            }
        };
        script.onerror = function() {
            callback(false);
        };
        document.head.appendChild(script);
    }

    /**
     * Editor instance management.
     */
    var EditorManager = {
        instances: {},

        /**
         * Initialize Ace Editor on a target textarea.
         *
         * @param {object} config Configuration parameters.
         */
        init: function(config) {
            var textareaId = config.textareaId || 'student_code';
            var wrapperId = config.wrapperId || 'tceEditorWrapper';
            var editorDivId = config.editorDivId || 'student_code_ace';
            var coderunnerType = config.coderunnerType || 'python3';
            var readOnly = Boolean(config.readOnly);

            var textarea = document.getElementById(textareaId);
            if (!textarea) {
                return;
            }

            // Single initialization guard.
            if (textarea.getAttribute('data-tce-editor-initialized') === '1') {
                return;
            }

            var wrapper = document.getElementById(wrapperId);
            var editorDiv = document.getElementById(editorDivId);
            if (!wrapper || !editorDiv) {
                return;
            }

            ensureAceLoaded(function(loaded) {
                if (!loaded) {
                    // Graceful fallback: Keep standard textarea visible.
                    textarea.classList.remove('d-none');
                    editorDiv.style.display = 'none';
                    wrapper.classList.add('tce-fallback-active');
                    return;
                }

                try {
                    // Configure Ace base paths for offline asset loading.
                    var baseUrl = M.cfg.wwwroot + '/local/testcase_exchange/ace/';
                    window.ace.config.set('basePath', baseUrl);
                    window.ace.config.set('modePath', baseUrl);
                    window.ace.config.set('themePath', baseUrl);
                    window.ace.config.set('workerPath', baseUrl);

                    var editor = window.ace.edit(editorDiv);
                    textarea.setAttribute('data-tce-editor-initialized', '1');

                    // Editor core settings.
                    editor.setOptions({
                        enableBasicAutocompletion: true,
                        enableLiveAutocompletion: true,
                        enableSnippets: false,
                        showLineNumbers: true,
                        showGutter: true,
                        tabSize: 4,
                        useSoftTabs: true,
                        fontSize: '14px',
                        newLineMode: 'unix',
                        wrap: false,
                        showPrintMargin: false,
                        behavioursEnabled: true,
                        scrollPastEnd: 0.2
                    });
                    editor.$blockScrolling = Infinity;

                    // Disable web workers to prevent cross-origin or 404 worker errors.
                    editor.getSession().setUseWorker(false);

                    // Syntax Mode Resolution.
                    var modeInfo = getAceModeForCoderunner(coderunnerType);
                    editor.getSession().setMode(modeInfo.mode);

                    // Set initial code directly from textarea (preserving Unicode, tabs, spaces, newlines).
                    var initialCode = textarea.value || '';
                    editor.setValue(initialCode, -1);
                    editor.clearSelection();

                    // Read-only state.
                    if (readOnly || textarea.hasAttribute('readonly') || textarea.hasAttribute('disabled')) {
                        editor.setReadOnly(true);
                        var readonlyBadge = document.getElementById('tceEditorReadonlyBadge');
                        if (readonlyBadge) {
                            readonlyBadge.classList.remove('d-none');
                        }
                    }

                    // Theme setup (persist in localStorage).
                    var currentTheme = window.localStorage.getItem(STORAGE_THEME_KEY) || THEME_LIGHT;
                    editor.setTheme(currentTheme);
                    EditorManager.updateThemeButton(currentTheme);

                    // Bi-directional synchronization variables.
                    var isSyncingFromTextarea = false;

                    // 1. Sync: Ace -> Textarea (on every edit).
                    editor.getSession().on('change', function() {
                        if (isSyncingFromTextarea) {
                            return;
                        }
                        textarea.value = editor.getValue();
                        // Dispatch standard input and change events for existing auto-save listeners.
                        textarea.dispatchEvent(new Event('input', { bubbles: true }));
                    });

                    // 2. Sync: Textarea -> Ace (when external scripts update textarea.value).
                    function syncFromTextarea() {
                        var textVal = textarea.value || '';
                        if (editor.getValue() !== textVal) {
                            isSyncingFromTextarea = true;
                            editor.setValue(textVal, -1);
                            editor.clearSelection();
                            isSyncingFromTextarea = false;
                        }
                    }

                    textarea.addEventListener('change', syncFromTextarea);
                    textarea.addEventListener('tce_sync_from_textarea', syncFromTextarea);

                    // Also poll / listen for revision or load-code button clicks.
                    $(document).on('click', '.btn-apply-revision, .btn-load-run-code', function() {
                        setTimeout(syncFromTextarea, 50);
                    });

                    // 3. Sync immediately before form submit.
                    var parentForm = textarea.closest('form');
                    if (parentForm) {
                        parentForm.addEventListener('submit', function() {
                            textarea.value = editor.getValue();
                        }, true);
                    }

                    // Status Bar: Cursor Position.
                    var cursorPosEl = document.getElementById('tceEditorCursorPos');
                    function updateCursorPos() {
                        if (!cursorPosEl) return;
                        var pos = editor.getCursorPosition();
                        cursorPosEl.textContent = 'Dòng ' + (pos.row + 1) + ', Cột ' + (pos.column + 1);
                    }
                    editor.selection.on('changeCursor', updateCursorPos);
                    updateCursorPos();

                    // Fullscreen toggle.
                    var fullscreenBtn = document.getElementById('tceToggleFullscreenBtn');
                    if (fullscreenBtn) {
                        fullscreenBtn.addEventListener('click', function() {
                            EditorManager.toggleFullscreen(wrapper, editor);
                        });
                    }

                    // Theme toggle button.
                    var themeBtn = document.getElementById('tceToggleThemeBtn');
                    if (themeBtn) {
                        themeBtn.addEventListener('click', function() {
                            var newTheme = (editor.getTheme() === THEME_DARK) ? THEME_LIGHT : THEME_DARK;
                            editor.setTheme(newTheme);
                            window.localStorage.setItem(STORAGE_THEME_KEY, newTheme);
                            EditorManager.updateThemeButton(newTheme);
                        });
                    }

                    // Escape key exits fullscreen.
                    document.addEventListener('keydown', function(e) {
                        if (e.key === 'Escape' && wrapper.classList.contains('tce-fullscreen')) {
                            EditorManager.toggleFullscreen(wrapper, editor);
                        }
                    });

                    // Window resize adjustment.
                    window.addEventListener('resize', function() {
                        editor.resize();
                    });

                    // Store instance.
                    EditorManager.instances[textareaId] = {
                        editor: editor,
                        textarea: textarea,
                        wrapper: wrapper,
                        syncFromTextarea: syncFromTextarea
                    };

                    // Reveal editor, hide original textarea.
                    textarea.classList.add('d-none');
                    editorDiv.style.display = 'block';
                    editor.resize();

                } catch (err) {
                    console.error('TCE: Ace Editor initialization failed, falling back to standard textarea:', err);
                    textarea.classList.remove('d-none');
                    editorDiv.style.display = 'none';
                    wrapper.classList.add('tce-fallback-active');
                }
            });
        },

        /**
         * Toggle fullscreen state for editor.
         */
        toggleFullscreen: function(wrapper, editor) {
            var isFullscreen = wrapper.classList.toggle('tce-fullscreen');
            var icon = document.getElementById('tceFullscreenIcon');
            var text = document.getElementById('tceFullscreenText');

            if (isFullscreen) {
                document.body.classList.add('tce-noscroll');
                if (icon) icon.className = 'fa fa-compress mr-1';
                if (text) text.textContent = 'Thu nhỏ';
            } else {
                document.body.classList.remove('tce-noscroll');
                if (icon) icon.className = 'fa fa-arrows-alt mr-1';
                if (text) text.textContent = 'Toàn màn hình';
            }

            setTimeout(function() {
                editor.resize();
                editor.focus();
            }, 100);
        },

        /**
         * Update theme toggle button label and icon.
         */
        updateThemeButton: function(theme) {
            var icon = document.getElementById('tceThemeIcon');
            var text = document.getElementById('tceThemeText');
            if (!icon || !text) return;

            if (theme === THEME_DARK) {
                icon.className = 'fa fa-sun-o mr-1';
                text.textContent = 'Giao diện sáng';
            } else {
                icon.className = 'fa fa-moon-o mr-1';
                text.textContent = 'Giao diện tối';
            }
        },

        /**
         * Get active editor instance for a textarea.
         */
        getInstance: function(textareaId) {
            return EditorManager.instances[textareaId || 'student_code'] || null;
        }
    };

    return EditorManager;
});

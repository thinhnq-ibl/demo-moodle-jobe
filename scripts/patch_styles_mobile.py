with open('local_testcase_exchange/styles.css', 'r', encoding='utf-8') as f:
    content = f.read()

target = """.local-testcase-exchange-root {
    font-family: inherit;
    font-size: 1rem;
    line-height: 1.5;
    color: #1e293b; /* High contrast primary body text (13:1) */
    width: 100%;
    max-width: 100%;
}"""

replacement = """.local-testcase-exchange-root {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", "Liberation Sans", sans-serif;
    font-size: 1rem;
    line-height: 1.5;
    color: #1e293b; /* High contrast primary body text (13:1) */
    width: 100%;
    max-width: 100%;
}

/* Explicit sans-serif inheritance for all UI components */
.local-testcase-exchange-root,
.local-testcase-exchange-root h1,
.local-testcase-exchange-root h2,
.local-testcase-exchange-root h3,
.local-testcase-exchange-root h4,
.local-testcase-exchange-root h5,
.local-testcase-exchange-root h6,
.local-testcase-exchange-root p,
.local-testcase-exchange-root span,
.local-testcase-exchange-root a,
.local-testcase-exchange-root label,
.local-testcase-exchange-root button,
.local-testcase-exchange-root input,
.local-testcase-exchange-root select,
.local-testcase-exchange-root textarea:not(.font-monospace),
.local-testcase-exchange-root .table,
.local-testcase-exchange-root .nav-tabs,
.local-testcase-exchange-root .nav-link,
.local-testcase-exchange-root .badge,
.local-testcase-exchange-root .card-header,
.local-testcase-exchange-root .modal-content {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", "Liberation Sans", sans-serif !important;
}"""

assert target in content, "target not found in styles.css"
content = content.replace(target, replacement, 1)

# Append mobile optimization rules
mobile_rules = """
/* =========================================================================
   MOBILE HEADER, HORIZONTAL TAB SCROLL & COMPACT RHYTHM
   ========================================================================= */
.tce-nav-tabs-scroll {
    display: flex;
    flex-wrap: nowrap !important;
    overflow-x: auto !important;
    -webkit-overflow-scrolling: touch;
    white-space: nowrap !important;
    border-bottom: 2px solid #dee2e6;
    padding-bottom: 1px;
}
.tce-nav-tabs-scroll .nav-item {
    flex: 0 0 auto !important;
}
.tce-nav-tabs-scroll::-webkit-scrollbar {
    height: 3px;
}
.tce-nav-tabs-scroll::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 3px;
}

@media (max-width: 767.98px) {
    .local-testcase-exchange-root .tce-header-bar {
        margin-bottom: 0.5rem !important;
        padding-bottom: 0.4rem !important;
    }
    .local-testcase-exchange-root .tce-header-title h4,
    .local-testcase-exchange-root .tce-header-title h3 {
        font-size: 1.1rem !important;
    }
    .local-testcase-exchange-root .tce-header-subtitle {
        font-size: 0.8rem !important;
    }
    .local-testcase-exchange-root .tce-nav-tabs-scroll .nav-link {
        padding: 0.4rem 0.65rem !important;
        font-size: 0.85rem !important;
    }
    .local-testcase-exchange-root .tce-stat-card-mobile {
        padding: 0.5rem 0.6rem !important;
    }
    .local-testcase-exchange-root .tce-stat-card-mobile .h4 {
        font-size: 1.15rem !important;
    }
}
"""

content = content.rstrip() + "\n" + mobile_rules

with open('local_testcase_exchange/styles.css', 'w', encoding='utf-8') as f:
    f.write(content)

print("Updated styles.css successfully")

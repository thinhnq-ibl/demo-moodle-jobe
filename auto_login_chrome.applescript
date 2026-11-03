tell application "Google Chrome"
    activate
    set URL of active tab of front window to "http://localhost:8080/login/index.php"
    delay 2
end tell

tell application "System Events"
    tell process "Google Chrome"
        -- Click into username field or Tab to it
        keystroke "admin"
        keystroke tab
        keystroke "AdminPassword123!"
        keystroke return
    end tell
end tell

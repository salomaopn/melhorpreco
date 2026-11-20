#!/bin/bash
unset LD_LIBRARY_PATH
export HOME="/opt/lampp/htdocs/melhorpreco/chrome_profile"
export XDG_CONFIG_HOME="/opt/lampp/htdocs/melhorpreco/chrome_profile/.config"
export XDG_CACHE_HOME="/opt/lampp/htdocs/melhorpreco/chrome_profile/.cache"
exec /usr/bin/google-chrome "$@"

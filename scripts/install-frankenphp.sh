#!/usr/bin/env bash

set -euo pipefail

project_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
frankenphp_version="1.13.4"
check_runtime='exit(PHP_MAJOR_VERSION === 8 && PHP_MINOR_VERSION === 4 && function_exists("mb_split") && extension_loaded("pdo_pgsql") && extension_loaded("zip") ? 0 : 1);'
if [ -x "${project_dir}/frankenphp" ] && "${project_dir}/frankenphp" php-cli -r "$check_runtime"; then
    installed_version="$("${project_dir}/frankenphp" version)"
    if [[ "$installed_version" == "FrankenPHP v${frankenphp_version} "* ]]; then
        echo "$installed_version"
        exit 0
    fi
fi
build_dir="${FRANKENPHP_BUILD_DIR:-${TMPDIR:-/tmp}/alliance-frankenphp-${frankenphp_version}-php84}"
source_dir="${build_dir}/frankenphp-${frankenphp_version}"

case "$(uname -s)" in
    Darwin) binary_name="frankenphp-mac-$(uname -m)" ;;
    Linux) binary_name="frankenphp-linux-$(uname -m)" ;;
    *) echo 'გამოიყენე macOS ან Linux/WSL.' >&2; exit 1 ;;
esac

mkdir -p "$build_dir"
if [ ! -f "${source_dir}/build-static.sh" ]; then
    curl --fail --location --retry 3 \
        "https://github.com/php/frankenphp/archive/refs/tags/v${frankenphp_version}.tar.gz" \
        --output "${build_dir}/source.tar.gz"
    tar -xzf "${build_dir}/source.tar.gz" -C "$build_dir"
fi

binary="${source_dir}/dist/${binary_name}"
if [ ! -x "$binary" ] || ! "$binary" php-cli -r "$check_runtime"; then
    (
        cd "$source_dir"
        export FRANKENPHP_VERSION="$frankenphp_version"
        export PHP_VERSION=8.4
        export GODEBUG="${GODEBUG:+${GODEBUG},}http2client=0"
        export PHP_EXTENSIONS=bcmath,ctype,curl,dom,fileinfo,filter,gd,iconv,intl,mbregex,mbstring,opcache,openssl,pcntl,pdo,pdo_pgsql,pdo_sqlite,phar,posix,session,simplexml,sockets,sodium,sqlite3,tokenizer,xml,xmlreader,xmlwriter,zip,zlib
        export PHP_EXTENSION_LIBS=brotli,watcher
        # Build the selected extensions with their required dependencies.
        php -r '$path = "craft.yml"; file_put_contents($path, str_replace("with-suggests: true", "with-suggests: false", file_get_contents($path)));'
        bash build-static.sh
    )
fi

"$binary" php-cli -r "$check_runtime"
install -m 755 "$binary" "${project_dir}/frankenphp"
"${project_dir}/frankenphp" version

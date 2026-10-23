#!/usr/bin/env bash
#
# manticore-fmt installer: downloads the release binary for this platform, verifies it against the release's
# SHA256SUMS and installs it into $MANTICORE_FMT_HOME (default ~/.manticore-fmt).
#
#   curl -fsSL https://github.com/manticorephp/fmt/releases/latest/download/install.sh | bash
#
# Env: MANTICORE_FMT_BASE (release asset base URL, for mirrors and tests), MANTICORE_FMT_HOME, MANTICORE_FMT_VERSION (e.g. 0.1.0; default: latest), MANTICORE_FMT_REPO (default manticorephp/fmt).
# Needs curl or wget and a sha256 tool. No compiler. The binary links the system libpcre2-8, libssl/libcrypto and libz.
set -euo pipefail

PREFIX="${MANTICORE_FMT_HOME:-$HOME/.manticore-fmt}"
REPO="${MANTICORE_FMT_REPO:-manticorephp/fmt}"
die() { printf 'error: %s\n' "$*" >&2; exit 1; }
have() { command -v "$1" >/dev/null 2>&1; }
fetch() {
    if have curl; then curl -fsSL --retry 3 "$1" -o "$2"
    elif have wget; then wget -qO "$2" "$1"
    else die "neither curl nor wget found"
    fi
}

case "$(uname -s)" in
    Darwin) os=macos ;;
    Linux)
        os=linux
        if { ldd --version 2>&1 || true; } | grep -qi musl || ls /lib/ld-musl-* >/dev/null 2>&1; then os=linux-musl; fi ;;
    *) die "unsupported OS $(uname -s)" ;;
esac
case "$(uname -m)" in
    arm64|aarch64) arch=arm64 ;;
    x86_64|amd64)  arch=amd64 ;;
    *) die "unsupported arch $(uname -m)" ;;
esac
[ "$os-$arch" != macos-amd64 ] || die "no macOS x86_64 build; build from source (CONTRIBUTING.md)"

ver="${MANTICORE_FMT_VERSION:-}"; ver="${ver#v}"
tmp="$(mktemp -d)"; trap 'rm -rf "$tmp"' EXIT
if [ -n "${MANTICORE_FMT_BASE:-}" ]; then
    base="$MANTICORE_FMT_BASE"   # a mirror or a local directory (file://...)
elif [ -n "$ver" ]; then
    base="https://github.com/$REPO/releases/download/v$ver"
else
    base="https://github.com/$REPO/releases/latest/download"
fi
# With no version, "latest" redirects; the tarball name carries the version, so read it from SHA256SUMS.
fetch "$base/SHA256SUMS" "$tmp/SHA256SUMS" || die "cannot fetch $base/SHA256SUMS"
name="$(sed -n "s|^[0-9a-f]\{64\}[ *]*\./\{0,1\}\(manticore-fmt-.*-$os-$arch\.tar\.gz\)\$|\1|p" "$tmp/SHA256SUMS" | head -1)"
[ -n "$name" ] || die "this release has no build for $os-$arch"
fetch "$base/$name" "$tmp/$name" || die "cannot fetch $base/$name"

sum=sha256sum; have sha256sum || sum="shasum -a 256"
want="$(sed -n "s|^\([0-9a-f]\{64\}\)[ *]*\./\{0,1\}$name\$|\1|p" "$tmp/SHA256SUMS" | head -1)"
got="$($sum "$tmp/$name" | cut -d' ' -f1)"
[ "$want" = "$got" ] || die "checksum mismatch for $name (want $want, got $got)"

tar -xzf "$tmp/$name" -C "$tmp"
dir="${name%.tar.gz}"
[ -x "$tmp/$dir/bin/manticore-fmt" ] || die "$name holds no bin/manticore-fmt"
mkdir -p "$PREFIX/bin"
rm -f "$PREFIX/bin/manticore-fmt"    # macOS SIGKILLs a signed binary copied over; remove first
cp "$tmp/$dir/bin/manticore-fmt" "$PREFIX/bin/manticore-fmt"
[ "$os" != macos ] || xattr -d com.apple.quarantine "$PREFIX/bin/manticore-fmt" 2>/dev/null || true

if ! out="$("$PREFIX/bin/manticore-fmt" version 2>&1)"; then
    printf '%s\n' "$out" >&2
    case "$os" in
        macos)      hint="brew install pcre2 openssl@3" ;;
        linux-musl) hint="apk add pcre2 libssl3 zlib" ;;
        *)          hint="apt-get install libpcre2-8-0 libssl3 zlib1g   (or your distribution's equivalents)" ;;
    esac
    die "the installed binary does not start; it needs libpcre2-8, libssl/libcrypto and libz: $hint"
fi
echo "installed: $out -> $PREFIX/bin/manticore-fmt"
case ":$PATH:" in
    *":$PREFIX/bin:"*) ;;
    *) echo "add it to your PATH:  export PATH=\"$PREFIX/bin:\$PATH\"" ;;
esac

#!/usr/bin/env bash
#
# Fetch the pinned Manticore compiler (the release named in COMPILER_VERSION) for this host into
# <dest>/bin/manticore + <dest>/lib/ (default: <repo>/.compiler).
#
#   tools/fetch-compiler.sh [dest]
#
# The tarball MUST match the release's SHA256SUMS. Needs curl or wget and a sha256 tool; no php.
# Building also needs the compiler's own toolchain on the host: clang (to assemble IR), a C linker, and the dev
# libraries it links (pcre2, openssl, zlib; sqlite/curl/icu are only needed if a program uses them).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
dest="${1:-$ROOT/.compiler}"
ver="$(tr -d '[:space:]' < "$ROOT/COMPILER_VERSION")"
repo="${MANTICORE_GITHUB:-manticorephp/compiler}"

die() { echo "fetch-compiler: $*" >&2; exit 1; }
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
        if { ldd --version 2>&1 || true; } | grep -qi musl || ls /lib/ld-musl-* >/dev/null 2>&1; then
            os=linux-musl
        fi ;;
    *) die "unsupported OS $(uname -s)" ;;
esac
case "$(uname -m)" in
    arm64|aarch64) arch=arm64 ;;
    x86_64|amd64)  arch=amd64 ;;
    *) die "unsupported arch $(uname -m)" ;;
esac

name="manticore-$ver-$os-$arch"
url="https://github.com/$repo/releases/download/v$ver"
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

echo "compiler: v$ver ($os-$arch) from $url"
fetch "$url/$name.tar.gz" "$tmp/$name.tar.gz" \
    || die "no $name.tar.gz in release v$ver — is COMPILER_VERSION a published release for this platform?"
fetch "$url/SHA256SUMS" "$tmp/SHA256SUMS" || die "release v$ver has no SHA256SUMS"

sum=sha256sum
have sha256sum || sum="shasum -a 256"
want="$(sed -n "s|^\([0-9a-f]\{64\}\)[ *]*\./\{0,1\}$name\.tar\.gz\$|\1|p" "$tmp/SHA256SUMS" | head -1)"
got="$($sum "$tmp/$name.tar.gz" | cut -d' ' -f1)"
[ -n "$want" ] || die "SHA256SUMS of v$ver does not list $name.tar.gz"
[ "$want" = "$got" ] || die "checksum mismatch for $name.tar.gz (want $want, got $got)"

tar -xzf "$tmp/$name.tar.gz" -C "$tmp"
[ -x "$tmp/$name/bin/manticore" ] || die "$name.tar.gz holds no bin/manticore"

mkdir -p "$dest/bin" "$dest/lib"
# macOS SIGKILLs a binary copied OVER a signed one: remove first.
rm -f "$dest/bin/manticore"
cp "$tmp/$name/bin/manticore" "$dest/bin/manticore"
cp -R "$tmp/$name/lib/." "$dest/lib/"
[ "$os" != macos ] || xattr -d com.apple.quarantine "$dest/bin/manticore" 2>/dev/null || true

got="$("$dest/bin/manticore" version 2>/dev/null || true)"
[ "$got" = "manticore $ver" ] || die "the fetched compiler does not run here (version said '${got:-nothing}')"
echo "compiler: $got -> $dest/bin/manticore"

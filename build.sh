#!/bin/bash
# ============================================================
# build.sh - Skrip Pembinaan Pakej Bahasa ms-MY Joomla 6
# ============================================================
# Penggunaan: ./build.sh
# Output: dist/ms-MY_joomla_lang_full_<version>.zip
# ============================================================

set -e

# --- Konfigurasi ---
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
DIST_DIR="$SCRIPT_DIR/dist"
TEMP_DIR="$SCRIPT_DIR/_build_temp"

# Baca versi dari pkg_ms-MY.xml
VERSION=$(grep -oP '<version>\K[^<]+' "$SCRIPT_DIR/pkg_ms-MY.xml")
JOOMLA_VERSION=$(echo "$VERSION" | sed 's/\.[^.]*$//')

echo "=== Pembinaan Pakej Bahasa ms-MY ==="
echo "Versi: $VERSION"
echo ""

# --- Bersihkan ---
rm -rf "$TEMP_DIR" "$DIST_DIR"
mkdir -p "$TEMP_DIR" "$DIST_DIR"

# --- Fungsi: Jana install.xml ---
generate_install_xml() {
    local client="$1"
    local description="$2"
    local source_dir="$3"
    local output="$source_dir/install.xml"

    local file_entries=""
    for f in $(find "$source_dir" -maxdepth 1 -type f ! -name "install.xml" ! -name "langmetadata.xml" | sort); do
        file_entries="$file_entries\t\t<filename>$(basename "$f")</filename>\n"
    done

    cat > "$output" << EOF
<?xml version="1.0" encoding="UTF-8"?>
<extension client="$client" type="language" method="upgrade">
	<name>Malay (ms-MY)</name>
	<tag>ms-MY</tag>
	<version>$JOOMLA_VERSION</version>
	<creationDate>$(date +%Y-%m)</creationDate>
	<author>Joomla! Malaysia</author>
	<authorEmail>zaki@joomla.my</authorEmail>
	<authorUrl>www.joomla.my</authorUrl>
	<copyright>(C) $(date +%Y) Joomla! Malaysia</copyright>
	<license>GNU General Public License version 2 or later; see LICENSE.txt</license>
	<description>$description</description>
	<files>
		<filename file="meta">install.xml</filename>
		<filename file="meta">langmetadata.xml</filename>
$(echo -e "$file_entries")	</files>
	<params />
</extension>
EOF
}

# --- Senarai seksyen ---
declare -A SECTIONS_CLIENT=( [site]="site" [admin]="administrator" [api]="api" )
declare -A SECTIONS_SOURCE=( [site]="$SCRIPT_DIR/language/ms-MY" [admin]="$SCRIPT_DIR/administrator/language/ms-MY" [api]="$SCRIPT_DIR/api/language/ms-MY" )
declare -A SECTIONS_ZIP=( [site]="site_ms-MY.zip" [admin]="admin_ms-MY.zip" [api]="api_ms-MY.zip" )
declare -A SECTIONS_DESC=( [site]="Pakej Bahasa Melayu (ms-MY) - Laman" [admin]="Pakej Bahasa Melayu (ms-MY) - Pentadbir" [api]="Pakej Bahasa Melayu (ms-MY) - API" )

for section in site admin api; do
    client="${SECTIONS_CLIENT[$section]}"
    source_dir="${SECTIONS_SOURCE[$section]}"
    zip_name="${SECTIONS_ZIP[$section]}"
    description="${SECTIONS_DESC[$section]}"
    zip_path="$TEMP_DIR/$zip_name"

    echo "[$section] Menjana install.xml..."
    generate_install_xml "$client" "$description" "$source_dir"

    file_count=$(find "$source_dir" -maxdepth 1 -type f | wc -l)
    echo "[$section] $file_count fail dijumpai"

    echo "[$section] Mencipta $zip_name..."
    (cd "$source_dir" && zip -r "$zip_path" . -x "*/.*" > /dev/null)

    zip_size=$(du -k "$zip_path" | cut -f1)
    echo "[$section] $zip_name dicipta (${zip_size} KB)"
done

# --- Cipta pakej ZIP utama ---
echo ""
echo "Mencipta pakej utama..."

cp "$SCRIPT_DIR/pkg_ms-MY.xml" "$TEMP_DIR/"
cp "$SCRIPT_DIR/script.php" "$TEMP_DIR/"

PACKAGE_NAME="ms-MY_joomla_lang_full_${VERSION}.zip"
PACKAGE_PATH="$DIST_DIR/$PACKAGE_NAME"

(cd "$TEMP_DIR" && zip -r "$PACKAGE_PATH" . -x "*/.*" > /dev/null)

# --- Bersihkan temp ---
rm -rf "$TEMP_DIR"

# --- Ringkasan ---
PACKAGE_SIZE=$(du -k "$PACKAGE_PATH" | cut -f1)
echo ""
echo "=== SELESAI ==="
echo "Pakej: dist/$PACKAGE_NAME (${PACKAGE_SIZE} KB)"
echo ""
echo "Kandungan pakej:"
unzip -l "$PACKAGE_PATH" | grep -E "\.zip$|\.xml$|\.php$" | awk '{print "  " $4 " (" $1/1024 " KB)"}'

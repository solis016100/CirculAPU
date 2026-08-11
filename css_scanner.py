import re
from pathlib import Path

# Project folder = where this script is located
PROJECT = Path(__file__).parent

# Files that can contain class names
SOURCE_EXTENSIONS = {".php", ".html", ".htm", ".js"}

# CSS files to scan
CSS_EXTENSIONS = {".css"}

IGNORE_CSS_FILES = {
    "style.backup.css",
}

# Folders/files we don't want to scan
IGNORE_DIRS = {
    ".git",
    "node_modules",
}

print("=" * 60)
print("CSS UNUSED CLASS SCANNER")
print("=" * 60)

# ---------------------------------------------------------
# 1. Read all PHP / HTML / JS files
# ---------------------------------------------------------

source_files = []

for path in PROJECT.rglob("*"):
    if not path.is_file():
        continue

    if any(part in IGNORE_DIRS for part in path.parts):
        continue

    if path.suffix.lower() in SOURCE_EXTENSIONS:
        source_files.append(path)

print(f"\nScanning {len(source_files)} PHP/HTML/JS files...")

source_text = ""

for path in source_files:
    try:
        source_text += "\n" + path.read_text(
            encoding="utf-8",
            errors="ignore"
        )
    except Exception as e:
        print(f"Could not read: {path} ({e})")

# ---------------------------------------------------------
# 2. Find class names used in PHP / HTML / JS
# ---------------------------------------------------------

used_classes = set()

# Normal HTML:
# class="container login active"
class_attributes = re.findall(
    r'class\s*=\s*["\']([^"\']+)["\']',
    source_text,
    re.IGNORECASE
)

for value in class_attributes:
    for class_name in re.findall(r'[-_a-zA-Z0-9]+', value):
        used_classes.add(class_name)

# JavaScript:
# classList.add("active")
# classList.remove("active")
# classList.toggle("active")
js_classes = re.findall(
    r'classList\.(?:add|remove|toggle)\s*\(\s*["\']([^"\']+)["\']',
    source_text,
    re.IGNORECASE
)

for value in js_classes:
    used_classes.add(value)

# ---------------------------------------------------------
# 3. Find CSS class selectors
# ---------------------------------------------------------

css_files = []

for path in PROJECT.rglob("*.css"):
    if any(part in IGNORE_DIRS for part in path.parts):
        continue

    if path.name in IGNORE_CSS_FILES:
        continue

    css_files.append(path)

print(f"Scanning {len(css_files)} CSS files...")

all_css_classes = set()
css_class_locations = {}

for css_file in css_files:

    try:
        css_text = css_file.read_text(
            encoding="utf-8",
            errors="ignore"
        )
    except Exception as e:
        print(f"Could not read: {css_file} ({e})")
        continue

    # Remove CSS comments
    css_text = re.sub(
        r'/\*.*?\*/',
        '',
        css_text,
        flags=re.DOTALL
    )

    # Find .class selectors
    matches = re.finditer(
        r'(?<![\w-])\.([a-zA-Z_][\w-]*)',
        css_text
    )

    for match in matches:
        class_name = match.group(1)

        all_css_classes.add(class_name)

        if class_name not in css_class_locations:
            css_class_locations[class_name] = []

        css_class_locations[class_name].append(
            str(css_file.relative_to(PROJECT))
        )

# ---------------------------------------------------------
# 4. Compare
# ---------------------------------------------------------

used = sorted(all_css_classes & used_classes)
unused = sorted(all_css_classes - used_classes)

# ---------------------------------------------------------
# 5. Display results
# ---------------------------------------------------------

print("\n" + "=" * 60)
print("RESULT")
print("=" * 60)

print(f"\nCSS classes found : {len(all_css_classes)}")
print(f"Used classes      : {len(used)}")
print(f"Possibly unused   : {len(unused)}")

print("\n" + "-" * 60)
print("POSSIBLY UNUSED CLASSES")
print("-" * 60)

if unused:
    for class_name in unused:
        locations = sorted(set(css_class_locations[class_name]))

        print(f"\n.{class_name}")

        for location in locations:
            print(f"    {location}")
else:
    print("\nNo unused classes found.")

# ---------------------------------------------------------
# 6. Save report
# ---------------------------------------------------------

report_file = PROJECT / "css_unused_report.txt"

with report_file.open(
    "w",
    encoding="utf-8"
) as report:

    report.write("CSS UNUSED CLASS REPORT\n")
    report.write("=" * 60 + "\n\n")

    report.write(
        f"CSS classes found : {len(all_css_classes)}\n"
    )

    report.write(
        f"Used classes      : {len(used)}\n"
    )

    report.write(
        f"Possibly unused   : {len(unused)}\n\n"
    )

    report.write("=" * 60 + "\n")
    report.write("POSSIBLY UNUSED CLASSES\n")
    report.write("=" * 60 + "\n")

    for class_name in unused:

        report.write(f"\n.{class_name}\n")

        locations = sorted(
            set(css_class_locations[class_name])
        )

        for location in locations:
            report.write(f"    {location}\n")

print("\n" + "=" * 60)
print(f"Report saved to: {report_file.name}")
print("=" * 60)
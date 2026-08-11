import re
from pathlib import Path
from collections import defaultdict

PROJECT = Path(__file__).parent

CSS_FILES = [
    PROJECT / "style.css",
    PROJECT / "Admin" / "admin_style.css",
    PROJECT / "Manager" / "manager_style.css",
    PROJECT / "Auditor" / "auditor_style.css",
    PROJECT / "Members" / "member_style.css",
]

SOURCE_EXTENSIONS = {
    ".php",
    ".html",
    ".htm",
    ".js",
}


# =========================================================
# Helpers
# =========================================================

def remove_comments(text):
    return re.sub(
        r"/\*.*?\*/",
        "",
        text,
        flags=re.DOTALL
    )


def normalize_property(prop):
    prop = re.sub(r"\s+", " ", prop.strip())
    return prop


def extract_properties(declarations):
    """
    Convert:

        display: flex;
        padding: 10px;
        color: red;

    into:

        {
            "display": "flex",
            "padding": "10px",
            "color": "red"
        }
    """

    properties = {}

    for part in declarations.split(";"):

        part = part.strip()

        if not part or ":" not in part:
            continue

        name, value = part.split(":", 1)

        name = normalize_property(name)
        value = normalize_property(value)

        properties[name] = value

    return properties


def normalize_declarations(text):

    properties = extract_properties(text)

    return ";".join(
        f"{key}:{properties[key]}"
        for key in sorted(properties)
    )


def extract_css_rules(css):

    rules = defaultdict(list)

    pattern = re.compile(
        r"([^{}]+)\{([^{}]*)\}",
        re.DOTALL
    )

    for match in pattern.finditer(css):

        selector = match.group(1).strip()
        declarations = match.group(2).strip()

        if not declarations:
            continue

        class_names = re.findall(
            r"\.([a-zA-Z_][\w-]*)",
            selector
        )

        if not class_names:
            continue

        properties = extract_properties(
            declarations
        )

        if not properties:
            continue

        normalized = normalize_declarations(
            declarations
        )

        for class_name in class_names:

            rules[class_name].append({

                "selector": selector,

                "properties": properties,

                "normalized": normalized,

            })

    return rules


# =========================================================
# Scan source files
# =========================================================

print("=" * 75)
print("CSS SIMILARITY ANALYZER")
print("=" * 75)

source_files = []

for path in PROJECT.rglob("*"):

    if not path.is_file():
        continue

    if path.suffix.lower() not in SOURCE_EXTENSIONS:
        continue

    source_files.append(path)


print(
    f"\nScanning {len(source_files)} "
    f"PHP/HTML/JS files..."
)


class_usage = defaultdict(set)

for path in source_files:

    try:
        text = path.read_text(
            encoding="utf-8",
            errors="ignore"
        )
    except Exception:
        continue

    # class="..."
    attributes = re.findall(
        r'class\s*=\s*["\']([^"\']+)["\']',
        text,
        re.IGNORECASE
    )

    for value in attributes:

        names = re.findall(
            r"[-_a-zA-Z0-9]+",
            value
        )

        for name in names:

            class_usage[name].add(
                str(path.relative_to(PROJECT))
            )

    # classList.add/remove/toggle
    js_matches = re.findall(
        r'classList\.(?:add|remove|toggle)'
        r'\s*\(\s*["\']([^"\']+)["\']',
        text,
        re.IGNORECASE
    )

    for value in js_matches:

        names = re.findall(
            r"[-_a-zA-Z0-9]+",
            value
        )

        for name in names:

            class_usage[name].add(
                str(path.relative_to(PROJECT))
            )


# =========================================================
# Scan CSS
# =========================================================

css_rules = defaultdict(list)

print("\nScanning CSS files...")

for css_file in CSS_FILES:

    if not css_file.exists():

        print(
            f"WARNING: {css_file.relative_to(PROJECT)} "
            f"not found"
        )

        continue

    css = css_file.read_text(
        encoding="utf-8",
        errors="ignore"
    )

    css = remove_comments(css)

    rules = extract_css_rules(css)

    print(
        f"{css_file.relative_to(PROJECT)}: "
        f"{len(rules)} classes"
    )

    for class_name, rule_list in rules.items():

        for rule in rule_list:

            css_rules[class_name].append({

                "file": str(
                    css_file.relative_to(PROJECT)
                ),

                "selector": rule["selector"],

                "properties": rule["properties"],

                "normalized": rule["normalized"],

            })


css_classes = set(css_rules.keys())


# =========================================================
# Similarity calculation
# =========================================================

def compare_rules(properties_a, properties_b):

    keys_a = set(properties_a.keys())
    keys_b = set(properties_b.keys())

    if not keys_a or not keys_b:
        return 0, set(), set()

    shared = set()

    for key in keys_a & keys_b:

        if properties_a[key] == properties_b[key]:

            shared.add(key)

    total = len(
        keys_a | keys_b
    )

    percentage = (
        len(shared) / total * 100
        if total
        else 0
    )

    return percentage, shared, keys_a | keys_b


# =========================================================
# Find high similarity pairs
# =========================================================

similar_pairs = []

class_names = sorted(css_classes)

for i in range(len(class_names)):

    class_a = class_names[i]

    # Only use first rule for now.
    # This keeps the report manageable.
    rule_a = css_rules[class_a][0]

    for j in range(i + 1, len(class_names)):

        class_b = class_names[j]

        rule_b = css_rules[class_b][0]

        percentage, shared, all_props = compare_rules(
            rule_a["properties"],
            rule_b["properties"]
        )

        files_are_different = (
            rule_a["file"] != rule_b["file"]
        )

        one_is_global = (
            rule_a["file"] == "style.css"
            or
            rule_b["file"] == "style.css"
        )

        if (
            len(shared) >= 3
            and percentage >= 60
            and files_are_different
            and one_is_global
        ):

            similar_pairs.append({

                "class_a": class_a,

                "class_b": class_b,

                "file_a": rule_a["file"],

                "file_b": rule_b["file"],

                "percentage": percentage,

                "shared": shared,

                "properties_a":
                    rule_a["properties"],

                "properties_b":
                    rule_b["properties"],

            })


# =========================================================
# Sort
# =========================================================

similar_pairs.sort(
    key=lambda x: (
        -x["percentage"],
        -len(x["shared"])
    )
)


# =========================================================
# Report
# =========================================================

print("\n")
print("=" * 75)
print("HIGH SIMILARITY CSS")
print("=" * 75)

if not similar_pairs:

    print("\nNo strong similarities found.")

else:

    for number, pair in enumerate(
        similar_pairs,
        1
    ):

        print(
            f"\n#{number} "
            f"{pair['percentage']:.0f}% similar"
        )

        print("-" * 75)

        print(
            f".{pair['class_a']}"
            f"  →  {pair['file_a']}"
        )

        print(
            f".{pair['class_b']}"
            f"  →  {pair['file_b']}"
        )

        print("\nShared properties:")

        for prop in sorted(
            pair["shared"]
        ):

            value = pair[
                "properties_a"
            ][prop]

            print(
                f"    {prop}: {value};"
            )

        print("\nUsage:")

        for class_name in [
            pair["class_a"],
            pair["class_b"]
        ]:

            files = sorted(
                class_usage.get(
                    class_name,
                    set()
                )
            )

            if files:

                print(
                    f"    .{class_name}:"
                )

                for file in files:

                    print(
                        f"        {file}"
                    )

            else:

                print(
                    f"    .{class_name}: "
                    f"NO USAGE FOUND"
                )


# =========================================================
# Save report
# =========================================================

report_path = (
    PROJECT /
    "css_similarity_report.txt"
)

with report_path.open(
    "w",
    encoding="utf-8"
) as report:

    report.write(
        "CSS SIMILARITY REPORT\n"
    )

    report.write(
        "=" * 75 + "\n\n"
    )

    report.write(
        f"CSS classes analyzed: "
        f"{len(css_classes)}\n"
    )

    report.write(
        f"Similar pairs found: "
        f"{len(similar_pairs)}\n\n"
    )

    for number, pair in enumerate(
        similar_pairs,
        1
    ):

        report.write(
            f"\n#{number} "
            f"{pair['percentage']:.0f}% similar\n"
        )

        report.write(
            f".{pair['class_a']} "
            f"→ {pair['file_a']}\n"
        )

        report.write(
            f".{pair['class_b']} "
            f"→ {pair['file_b']}\n"
        )

        report.write(
            "\nShared properties:\n"
        )

        for prop in sorted(
            pair["shared"]
        ):

            value = pair[
                "properties_a"
            ][prop]

            report.write(
                f"  {prop}: {value};\n"
            )

        report.write("\n")


print("\n")
print("=" * 75)
print(
    f"Similarity report saved to: "
    f"{report_path.name}"
)
print("=" * 75)
from pathlib import Path



def main() -> None:
    script_dir = Path(__file__).resolve().parent
    root_dir = script_dir.parent
    settings_path = root_dir / "includes" / "settings.php"
    template_path = script_dir / "aichat_settings_page.php"

    if not template_path.exists():
        raise FileNotFoundError("Missing template file: aichat_settings_page.php")

    template = template_path.read_text(encoding="utf-8").strip()
    if not template.startswith("function aichat_settings_page()"):
        raise ValueError("Template must start with function aichat_settings_page().")

    settings_text = settings_path.read_text(encoding="utf-8")

    try:
        marker_start = settings_text.index("function aichat_settings_page()")
    except ValueError as exc:
        raise ValueError("Could not locate aichat_settings_page() in settings.php") from exc

    try:
        marker_end = settings_text.index("/**\n * Sanitizers")
    except ValueError as exc:
        raise ValueError("Could not locate sanitizers marker in settings.php") from exc

    updated = settings_text[:marker_start] + template + "\n\n" + settings_text[marker_end:]
    settings_path.write_text(updated, encoding="utf-8")


if __name__ == "__main__":
    main()

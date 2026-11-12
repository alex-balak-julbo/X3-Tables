#!/usr/bin/env python3
"""
Extrait le dictionnaire des tables Sage X3 (aide en ligne V12) vers du JSON.

Installation :  pip install requests beautifulsoup4
Usage        :  python scrape_x3_tables.py                 # toutes les tables
                python scrape_x3_tables.py ITMMASTER BPCUSTOMER   # tables ciblées

Sorties :
  x3_json/<TABLE>.json   un fichier par table
  x3_tables.json         bibliothèque complète (toutes les tables)
  x3_search_index.json   index plat des champs (pour recherche rapide)
"""
import json
import re
import sys
import time
from pathlib import Path
from urllib.parse import urljoin

import requests
from bs4 import BeautifulSoup

BASE = "https://online-help.sagex3.com/erp/12/fr-fr/Content/MCD/"
INDEX_URL = BASE + "ATB_0.htm"
OUT_DIR = Path("x3_json")
DELAY = 0.3  # secondes entre deux requêtes (politesse)

session = requests.Session()
session.headers["User-Agent"] = "Mozilla/5.0 (x3-dictionary-export)"


def get_soup(url, retries=3):
    for i in range(retries):
        try:
            r = session.get(url, timeout=30)
            r.raise_for_status()
            r.encoding = "utf-8"
            return BeautifulSoup(r.text, "html.parser")
        except requests.RequestException as e:
            if i == retries - 1:
                raise
            time.sleep(2 * (i + 1))


def clean(cell):
    return re.sub(r"\s+", " ", cell.get_text(" ", strip=True)).strip()


def table_code_from_href(href):
    m = re.search(r"/MCD/([A-Za-z0-9_]+)\.htm$", href or "")
    return m.group(1) if m else None


def list_tables():
    """Retourne la liste [{table, abbreviation, label, module}] depuis l'index."""
    soup = get_soup(INDEX_URL)
    tables = {}
    for a in soup.find_all("a", href=True):
        code = table_code_from_href(urljoin(INDEX_URL, a["href"]))
        if not code or code.startswith(("ATY_", "MEN", "ATB_")):
            continue
        if clean(a) != code:
            continue
        row = a.find_parent("tr")
        if not row:
            continue
        cells = [clean(td) for td in row.find_all("td")]
        # On retire les colonnes "Modification" (versions) et le code activité
        info = {"table": code, "abbreviation": "", "label": "", "module": ""}
        try:
            idx = cells.index(code)
            rest = [c for c in cells[idx + 1:] if c != "Modification"]
            if len(rest) >= 1:
                info["abbreviation"] = rest[0]
            if len(rest) >= 2:
                info["label"] = rest[1]
            if len(rest) >= 3:
                info["module"] = rest[2]
        except ValueError:
            pass
        tables[code] = info
    return sorted(tables.values(), key=lambda t: t["table"])


def parse_table_page(code):
    url = BASE + code + ".htm"
    soup = get_soup(url)
    result = {"table": code, "url": url, "keys": [], "fields": []}

    for tbl in soup.find_all("table"):
        header = [clean(th) for th in tbl.find_all("th")]
        if not header:
            first = tbl.find("tr")
            header = [clean(c) for c in first.find_all(["td", "th"])] if first else []
        rows = tbl.find_all("tr")[1:]

        if header[:1] == ["Table"]:
            for r in rows:
                c = [clean(td) for td in r.find_all("td")]
                if len(c) >= 4:
                    result.update(abbreviation=c[1], label=c[2], module=c[3])

        elif header[:1] == ["Clé"]:
            for r in rows:
                c = [clean(td) for td in r.find_all("td")]
                if len(c) >= 3:
                    result["keys"].append({
                        "key": c[0],
                        "description": c[1],
                        "duplicates": c[2].lower() == "oui",
                        "activity": c[3] if len(c) > 3 else "",
                    })

        elif header[:1] == ["Colonne"]:
            for r in rows:
                td = r.find_all("td")
                c = [clean(x) for x in td]
                if len(c) < 4:
                    continue
                link_a = td[6].find("a") if len(td) > 6 else None
                link = table_code_from_href(link_a["href"]) if link_a and link_a.get("href") else ""
                result["fields"].append({
                    "name": c[0],
                    "label": c[1],
                    "dim": c[2],
                    "type": c[3],
                    "length": c[4] if len(c) > 4 else "",
                    "menu": c[5] if len(c) > 5 else "",
                    "link": link or "",
                    "link_expr": c[6] if len(c) > 6 else "",
                    "nullify": c[7] if len(c) > 7 else "",
                    "activity": c[8] if len(c) > 8 else "",
                })
    return result


def main():
    OUT_DIR.mkdir(exist_ok=True)
    wanted = sys.argv[1:]

    if wanted:
        index = {t["table"]: t for t in list_tables()}
        tables = [index.get(w, {"table": w}) for w in wanted]
    else:
        tables = list_tables()
    print(f"{len(tables)} tables à traiter")

    library, flat = [], []
    for n, t in enumerate(tables, 1):
        code = t["table"]
        out = OUT_DIR / f"{code}.json"
        if out.exists():  # reprise après interruption
            data = json.loads(out.read_text(encoding="utf-8"))
        else:
            try:
                data = parse_table_page(code)
                data.setdefault("module", t.get("module", ""))
            except Exception as e:
                print(f"[{n}/{len(tables)}] {code} ERREUR {e}")
                continue
            out.write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding="utf-8")
            time.sleep(DELAY)
        library.append(data)
        for f in data["fields"]:
            flat.append({"table": code, "name": f["name"], "label": f["label"],
                         "type": f["type"], "length": f["length"]})
        print(f"[{n}/{len(tables)}] {code}: {len(data['fields'])} champs")

    Path("x3_tables.json").write_text(json.dumps(library, ensure_ascii=False), encoding="utf-8")
    Path("x3_search_index.json").write_text(json.dumps(flat, ensure_ascii=False), encoding="utf-8")
    print("Terminé : x3_tables.json et x3_search_index.json")


if __name__ == "__main__":
    main()
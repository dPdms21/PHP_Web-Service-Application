# py/image/00_build_mapped_subset.py
import os, shutil, random, argparse, json
from pathlib import Path

MAPPING = {
    "WALKING": [
        "walking_the_dog",
        "running"
    ],
    "SITTING": [
        "reading",
        "using_a_computer",
        "watching_TV",
        "writing_on_a_book",
        "playing_guitar",
    ],
    "STANDING": [
        "taking_photos",
        "texting_message",
        "phoning",
        "waving_hands",
        "writing_on_a_board",
        "washing_dishes",
        "smoking",
        "playing_violin",
        "gardening",
        "pushing_a_cart",
    ]
}

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--src", default="data/Stanford40", help="Stanford40 root (class folders)")
    ap.add_argument("--dst", default="data/Stanford40_mapped3", help="output root")
    ap.add_argument("--max_per_class", type=int, default=800, help="limit per target class for balance")
    ap.add_argument("--seed", type=int, default=42)
    args = ap.parse_args()

    random.seed(args.seed)
    src = Path(args.src); dst = Path(args.dst)
    if dst.exists():
        print(f"[INFO] remove old dst: {dst}")
        shutil.rmtree(dst)
    for tgt, src_classes in MAPPING.items():
        (dst / tgt).mkdir(parents=True, exist_ok=True)
        pool = []
        for sc in src_classes:
            folder = src / sc
            if not folder.exists():
                print(f"[WARN] missing source class folder: {folder}")
                continue
            imgs = [p for p in folder.glob("*") if p.suffix.lower() in [".jpg",".jpeg",".png",".bmp"]]
            pool.extend(imgs)
        random.shuffle(pool)
        if args.max_per_class > 0:
            pool = pool[:args.max_per_class]
        for i, p in enumerate(pool):
            out = dst / tgt / f"{p.stem}_{i}{p.suffix.lower()}"
            shutil.copy2(p, out)
        print(f"[OK] {tgt}: {len(pool)} images")

    with open(dst / "label_map.json", "w", encoding="utf-8") as f:
        json.dump(MAPPING, f, ensure_ascii=False, indent=2)
    print(f"[DONE] built subset at: {dst}")

if __name__ == "__main__":
    main()

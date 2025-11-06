import os, json
import matplotlib.pyplot as plt
import pandas as pd

out = "outputs/image_action_fast"
csv_path = os.path.join(out, "metrics.csv")

df = pd.read_csv(csv_path)
train = df[df["split"]=="train"]
val = df[df["split"]=="val"]

epochs = sorted(set(train["epoch"].dropna()))

plt.figure()
plt.plot(epochs, train[train["metric"]=="accuracy"]["value"], label="train_acc")
plt.plot(epochs, val[val["metric"]=="accuracy"]["value"], label="val_acc")
plt.plot(epochs, train[train["metric"]=="loss"]["value"], label="train_loss")
plt.plot(epochs, val[val["metric"]=="loss"]["value"], label="val_loss")
plt.legend()
plt.title("MobilenetV3 - Accuracy/Loss (fixed)")
plt.savefig(os.path.join(out, "curves_fixed.png"), dpi=150, bbox_inches="tight")
plt.close()
print("Saved:", os.path.join(out, "curves_fixed.png"))

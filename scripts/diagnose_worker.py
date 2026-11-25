import sys
import os
import torch
import whisper

print("Python version:", sys.version)
print("Torch version:", torch.__version__)
print("CUDA available:", torch.cuda.is_available())
if torch.cuda.is_available():
    print("Device name:", torch.cuda.get_device_name(0))

print("Testing Whisper load_model('tiny')...")
try:
    m = whisper.load_model("tiny", device="cpu")
    print("Loaded tiny model successfully:", type(m))
except Exception as e:
    print("Error loading tiny model:", e)

print("Testing Whisper load_model('base')...")
try:
    m2 = whisper.load_model("base", device="cpu")
    print("Loaded base model successfully:", type(m2))
except Exception as e:
    print("Error loading base model:", e)

import cv2
import torch
import torch.nn.functional as F
import torchvision.transforms as transforms
from PIL import Image
import numpy as np
from ResEmoteNet import ResEmoteNet
from ultralytics import YOLO
import csv
from datetime import datetime
import os

from fitibit import get_recent_heart_rate, load_tokens, refresh_access_token
global average
average = 0.000

device = torch.device("mps" if torch.backends.mps.is_available() else "cpu")

emotions = ["happy", "surprise", "sad", "anger", "disgust", "fear", "neutral"]
model = ResEmoteNet().to(device)
checkpoint = torch.load("fer2013_model.pth", weights_only=True)
model.load_state_dict(checkpoint["model_state_dict"])
model.eval()

transform = transforms.Compose([
    transforms.Resize((64, 64)),
    transforms.Grayscale(num_output_channels=3),
    transforms.ToTensor(),
    transforms.Normalize(mean=[0.485, 0.456, 0.406], std=[0.229, 0.224, 0.225]),
])

video_capture = cv2.VideoCapture(0)

font = cv2.FONT_HERSHEY_SIMPLEX
font_scale = 1.5
font_color = (0, 255, 0)
thickness = 2
line_type = cv2.LINE_AA
max_emotion = ""

CSV_FILENAME = 'valence_data.csv'
if not os.path.isfile(CSV_FILENAME):
    with open(CSV_FILENAME, 'w', newline='') as csvfile:
        writer = csv.writer(csvfile)
        writer.writerow(['timestamp', 'valence'])

def detect_emotion(video_frame):
    vid_fr_tensor = transform(video_frame).unsqueeze(0).to(device)
    with torch.no_grad():
        outputs = model(vid_fr_tensor)
        probabilities = F.softmax(outputs, dim=1)
    scores = probabilities.cpu().numpy().flatten()
    rounded_scores = [round(score, 2) for score in scores]
    return rounded_scores

def get_max_emotion(x, y, w, h, video_frame):
    crop_img = video_frame[y:y+h, x:x+w]
    pil_crop_img = Image.fromarray(crop_img)
    rounded_scores = detect_emotion(pil_crop_img)    
    max_index = np.argmax(rounded_scores)
    return emotions[max_index]

def print_max_emotion(x, y, video_frame, max_emotion):
    global average
    cv2.putText(video_frame, max_emotion, (x - 15, y - 10), font, 1, font_color, thickness, line_type)
    cv2.putText(video_frame, "valence: "+ str(np.round(average, 2)), (x - 15, y - 40), font, 0.6, font_color, thickness, line_type)

def print_all_emotion(x, y, w, h, video_frame):
    crop_img = video_frame[y:y+h, x:x+w]
    pil_crop_img = Image.fromarray(crop_img)
    rounded_scores = detect_emotion(pil_crop_img)
    org = (x + w + 10, y - 20)
    for idx, emotion in enumerate(emotions):
        emotion_str = f"{emotion}: {rounded_scores[idx]:.2f}"
        cv2.putText(video_frame, emotion_str, (org[0], org[1] + 40*idx), font, 0.6, font_color, thickness, line_type)
    return rounded_scores

detector = YOLO("yolov11n-face.pt")

def detect_bounding_box(video_frame, counter):
    global max_emotion
    try:
        results = detector.predict(video_frame, conf=0.5, verbose=False)
        x, y, w, h = results[0].boxes.xywh[0].to(torch.int16).cpu().numpy()
        x, y = int(x - w//2), int(y - h//2)

        cv2.rectangle(video_frame, (x, y), (x + w, y + h), font_color, 2)
        if counter == 0:
            max_emotion = get_max_emotion(x, y, w, h, video_frame)
            
        print_max_emotion(x, y, video_frame, max_emotion)
        rounded_scores = print_all_emotion(x, y, w, h, video_frame)
        return np.array(rounded_scores) if counter == 0 else None
    
    except Exception as e:
        print(f"Detection error: {str(e)}")
        return None
    
def ewma(data, window):
    data = np.array(data)
    alpha = 2 / (window + 1.0)
    alpha_rev = 1 - alpha

    scale = 1 / alpha_rev
    n = data.shape[0]

    r = np.arange(n)
    scale_array = scale ** r
    offset = data[0] * alpha_rev ** (r+1)
    power = alpha * alpha_rev ** (n-1)

    cumsum = (data * power * scale_array).cumsum()
    out = offset + cumsum * scale_array[::-1]
    return out

def calculate_valence(scores):
    positive = scores[0:1].max()
    negative = scores[2:5].max()
    neutral = scores[6]

    if neutral < positive or neutral < negative:
        valence = positive / (positive + negative)
    else:
        valence = 0.5

    return valence

counter = 0
emotion_frequency = 10
heart_frequency = 100
beta = 10
valences = []
access_token, refresh_token = load_tokens()
recent_heart_rate = 0

while True:
    ret, frame = video_capture.read()
    if not ret:
        break

    scores = detect_bounding_box(frame, counter % emotion_frequency)
    if scores is not None:
        valence = calculate_valence(scores)
        valences.append(valence)
        average = np.round(ewma(valences, window=beta)[-1], 4)

        with open(CSV_FILENAME, 'a', newline='') as csvfile:
            writer = csv.writer(csvfile)
            writer.writerow([datetime.now().isoformat(), average, recent_heart_rate])

    cv2.imshow("ResEmoteNet", frame)
    
    if cv2.waitKey(1) & 0xFF == ord("q"):
        break

    if counter % heart_frequency == 0:
        recent_heart_rate = get_recent_heart_rate(access_token)
        if recent_heart_rate:
            print("Most recent heart rate:", recent_heart_rate)
        else:
            print("Access token expired or invalid. Attempting to refresh.")
            new_access_token = refresh_access_token(refresh_token)
            
            if new_access_token:
                recent_heart_rate = get_recent_heart_rate(new_access_token)
                if recent_heart_rate:
                    print("Most recent heart rate:", recent_heart_rate)
    
    counter = counter + 1

video_capture.release()
cv2.destroyAllWindows()
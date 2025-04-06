import requests
import json
import time
import base64

# Function to load tokens from the file
def load_tokens():
    with open('tokens.txt', 'r') as file:
        lines = file.readlines()
        access_token = lines[0].strip().split(": ")[1]
        refresh_token = lines[1].strip().split(": ")[1]
    return access_token, refresh_token

# Function to refresh the access token
def refresh_access_token(refresh_token):
    client_id = '23QD74'
    client_secret = '1afa89aaa2dcbedc0c66b7e967b55fc4'
    redirect_uri = 'http://localhost:3000'

    token_url = 'https://api.fitbit.com/oauth2/token'

    headers = {
        'Authorization': f'Basic {base64.b64encode(f"{client_id}:{client_secret}".encode("utf-8")).decode("utf-8")}'
    }

    data = {
        'client_id': client_id,
        'client_secret': client_secret,
        'refresh_token': refresh_token,
        'grant_type': 'refresh_token'
    }

    response = requests.post(token_url, headers=headers, data=data)
    
    if response.status_code == 200:
        tokens = response.json()
        new_access_token = tokens['access_token']
        new_refresh_token = tokens.get('refresh_token', refresh_token)  # Use new refresh token if provided
        with open('tokens.txt', 'w') as file:
            file.write(f"Access Token: {new_access_token}\n")
            file.write(f"Refresh Token: {new_refresh_token}\n")
        return new_access_token
    else:
        print("Error refreshing token:", response.status_code, response.text)
        return None

# Function to get the most recent heart rate data
def get_recent_heart_rate(access_token):
    heart_rate_url = 'https://api.fitbit.com/1/user/-/activities/heart/date/today/1d/1min.json'

    headers = {
        'Authorization': f'Bearer {access_token}'
    }

    response = requests.get(heart_rate_url, headers=headers)

    if response.status_code == 200:
        heart_rate_data = response.json()
        # Extract the most recent heart rate from the returned data
        try:
            # Assuming 'activities-heart' contains the heart rate data for the day
            last_entry = heart_rate_data["activities-heart-intraday"]["dataset"][-1]
            last_time = last_entry["time"]
            last_value = last_entry["value"]
            return last_value
        except KeyError as e:
            print("Error in heart rate data format:", e)
            return None
    else:
        print("Error fetching heart rate data:", response.status_code, response.text)
        return None

# Main function to manage token expiration and data fetching
# def main():
#     access_token, refresh_token = load_tokens()

#     recent_heart_rate = get_recent_heart_rate(access_token)

#     if recent_heart_rate:
#         print("Most recent heart rate:", recent_heart_rate)
#     else:
#         print("Access token expired or invalid. Attempting to refresh.")
#         new_access_token = refresh_access_token(refresh_token)
        
#         if new_access_token:
#             recent_heart_rate = get_recent_heart_rate(new_access_token)
#             if recent_heart_rate:
#                 print("Most recent heart rate:", recent_heart_rate)

# if __name__ == '__main__':
#     main()

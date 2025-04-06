import time
import requests
import csv
import os
import shutil
from requests.sessions import Session
from requests.exceptions import RequestException

API_ENDPOINT = "https://0c8d-138-38-223-170.ngrok-free.app/api/external-api"
CSV_FILENAME = 'valence_data.csv'
CSV_COPY = 'valence_data_copy.csv'
POLL_INTERVAL = 1  # Seconds between checks
CSRF_TIMEOUT = 5  # Seconds to wait for CSRF token

print(f"Initializing API sender with endpoint: {API_ENDPOINT}")
print(f"Monitoring CSV file: {CSV_FILENAME}")

def get_csrf_token(session: Session):
    try:
        print("Attempting to fetch CSRF token...")
        response = session.get(API_ENDPOINT, timeout=CSRF_TIMEOUT)
        
        if response.status_code != 200:
            print(f"Warning: Received status {response.status_code} when fetching CSRF token")
            return None
            
        if 'XSRF-TOKEN' in response.cookies:
            print("Successfully retrieved CSRF token")
            return response.cookies['XSRF-TOKEN']
            
        print("Warning: No CSRF token found in response cookies")
        print("Response cookies:", response.cookies)
        return None
        
    except RequestException as e:
        print(f"Error getting CSRF token: {str(e)}")
        return None
    
    except Exception as e:
        print(f"Unexpected error getting CSRF token: {str(e)}")
        return None

def send(session: Session, valence, tempo):
    try:
        headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'ngrok-skip-browser-warning': 'true'
        }

        payload = {"valence": valence, "tempo" : tempo}
        
        print(f"Sending valence {valence} to API...")
        print(f"Sending tempo {tempo} to API...")

        response = session.post(
            API_ENDPOINT,
            json=payload,
            headers=headers,
            timeout=5
        )
        
        print(f"API response: {response.status_code}")
        if response.status_code != 200:
            print(f"Response content: {response.text}")
            
        return response.status_code == 200
        
    except RequestException as e:
        print(f"Failed to send values {valence} {tempo}: {str(e)}")
        return False

def process_csv_copy():
    """Create a copy of the CSV, process it, then delete the copy"""
    try:
        if not os.path.exists(CSV_FILENAME):
            print("Original CSV file does not exist")
            return None, None

        print("Creating copy of CSV file...")
        shutil.copy2(CSV_FILENAME, CSV_COPY)
        
        with open(CSV_COPY, 'r') as f:
            reader = csv.reader(f)
            rows = list(reader)
            
            if len(rows) < 2:
                print("CSV copy doesn't contain enough data")
                return None, None
                
            last_row = rows[-1]
            if len(last_row) < 2:
                print("Invalid row format in CSV copy")
                return None, None
                
            return last_row[1], last_row[2], last_row[0]  # valence, tempo, timestamp
            
    except Exception as e:
        print(f"Error processing CSV copy: {str(e)}")
        return None, None, None
    
    finally:
        if os.path.exists(CSV_COPY):
            try:
                os.remove(CSV_COPY)
                print("Cleaned up CSV copy")
            except Exception as e:
                print(f"Error deleting CSV copy: {str(e)}")

def monitor_csv():
    last_valence = None
    last_tempo = None
    
    with requests.Session() as session:
        # Get CSRF token with timeout
        print("Initializing CSRF token...")
        csrf_token = get_csrf_token(session)
        
        if csrf_token:
            session.headers.update({'X-XSRF-TOKEN': csrf_token})
            print("CSRF token initialized successfully")
        else:
            print("Warning: Continuing without CSRF token")
        
        print("Entering monitoring loop...")
        while True:
            try:
                print("\n--- Checking for new data ---")
                
                current_valence, current_tempo, timestamp = process_csv_copy()
                
                if current_valence is None:
                    print("No valid data found, waiting...")
                    time.sleep(POLL_INTERVAL)
                    continue
                    
                try:
                    current_valence = float(current_valence)
                    print(f"Read valence value: {current_valence} (timestamp: {timestamp})")
                    print(f"New valence detected (previous: {last_valence}, new: {current_valence})")
                    
                    current_tempo = int(current_tempo)
                    print(f"Read tempo value: {current_tempo} (timestamp: {timestamp})")
                    print(f"New tempo detected (previous: {last_tempo}, new: {current_tempo})")
                    

                    if send(session, current_valence, current_tempo):
                        last_valence = current_valence
                        last_tempo = current_tempo
                        print("Successfully sent new values")
                    else:
                        print("Failed to send new values")
                        
                except ValueError:
                    print(f"Skipping invalid values: {current_valence} {current_tempo}")
                
            except Exception as e:
                print(f"Unexpected error in monitoring loop: {str(e)}")
            
            print(f"Waiting {POLL_INTERVAL} seconds before next check...")
            time.sleep(POLL_INTERVAL)

if __name__ == "__main__":
    print("Starting CSV monitor...")
    try:
        monitor_csv()

    except KeyboardInterrupt:
        print("\nStopping CSV monitor...")

    except Exception as e:
        print(f"Fatal error: {str(e)}")
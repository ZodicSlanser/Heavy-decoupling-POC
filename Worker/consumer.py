import pika
import json
import time
import requests
import threading

def process_message(ch, method, properties, body):
    """
    Process a single message. This function simulates image processing,
    then sends a callback notification to Laravel, and finally schedules
    the acknowledgment on the main thread.
    """
    data = json.loads(body)
    exam_id = data.get('exam_id')
    user_id = data.get('user_id')
    images = data.get('images', [])
    print(f"Received exam {exam_id} for user {user_id} with {len(images)} images.")

    # Simulate processing each image (e.g., running AI predictions)
    for img in images:
        print(f"Processing image: {img}")
        time.sleep(1)  # simulate delay per image

    print(f"Finished processing exam {exam_id}")

    # After processing, send a notification to Laravel
    try:
        payload = {"exam_id": exam_id, "message": "Processing complete"}
        # Update the URL if your Laravel app is hosted elsewhere
        response = requests.post('http://localhost:8000/api/job-complete', data=payload)
        if response.status_code == 200:
            print(f"Notification sent for exam {exam_id}")
        else:
            print(f"Failed to send notification for exam {exam_id}: {response.text}")
    except Exception as e:
        print(f"Error sending notification: {str(e)}")

    # Schedule the acknowledgment on the main thread
    ch.connection.add_callback_threadsafe(
        lambda: ch.basic_ack(delivery_tag=method.delivery_tag)
    )

def callback(ch, method, properties, body):
    """
    For every message received, spin up a new thread to process it.
    """
    thread = threading.Thread(target=process_message, args=(ch, method, properties, body))
    thread.start()

def main():
    connection = pika.BlockingConnection(pika.ConnectionParameters(host='localhost'))
    channel = connection.channel()
    channel.queue_declare(queue='exam_jobs', durable=True)
    
    # Increase prefetch_count to allow multiple messages concurrently.
    channel.basic_qos(prefetch_count=3)
    
    channel.basic_consume(queue='exam_jobs', on_message_callback=callback)
    print('Waiting for exam jobs. To exit press CTRL+C')
    channel.start_consuming()

if __name__ == '__main__':
    main()

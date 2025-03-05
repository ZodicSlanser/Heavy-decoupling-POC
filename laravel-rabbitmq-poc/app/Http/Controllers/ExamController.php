<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Exam;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Illuminate\Support\Facades\Log;

class ExamController extends Controller
{
    // POST /upload-image
    public function uploadImage(Request $request)
    {
        // Validate the request; note 'images' must be present and each file must be an image of max size 2MB
        $validated = $request->validate([
            'user_id'   => 'required|integer',
            'exam_id'   => 'nullable|integer',
            'images'    => 'required',
            'images.*'  => 'image|max:2048'
        ]);

        // If exam_id is provided, try to find the exam; if not, create a new exam
        if ($request->filled('exam_id')) {
            $exam = Exam::find($request->exam_id);
            if (!$exam) {
                return response()->json(['error' => 'Exam not found'], 404);
            }
        } else {
            $exam = new Exam();
            $exam->user_id = $validated['user_id'];
            $exam->images = []; // Initialize with an empty array
            $exam->save();
        }

        // Array to hold paths of the uploaded files
        $uploadedPaths = [];

        // Process each image file uploaded
        foreach ($request->file('images') as $image) {
            // Store the image in the "uploads" directory using the public disk
            $path = $image->store('uploads', 'public');
            $exam->addImage($path);
            $uploadedPaths[] = $path;
        }

        return response()->json([
            'exam_id'         => $exam->id,
            'uploaded_images' => count($exam->images),
            'uploaded_paths'  => $uploadedPaths
        ]);
    }

    // POST /finish-exam
    public function finishExam(Request $request)
    {
        $validated = $request->validate([
            'exam_id' => 'required|integer'
        ]);

        $exam = Exam::find($validated['exam_id']);
        if (!$exam) {
            return response()->json(['error' => 'Exam not found'], 404);
        }

        if (!$exam->hasEnoughImages()) {
            return response()->json(['error' => 'Not enough images uploaded.'], 400);
        }

        // Send a job to RabbitMQ with exam details
        $connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
        $channel = $connection->channel();
        $channel->queue_declare('exam_jobs', false, true, false, false);

        $data = json_encode([
            'exam_id' => $exam->id,
            'user_id' => $exam->user_id,
            'images' => $exam->images
        ]);

        $msg = new AMQPMessage($data, ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
        $channel->basic_publish($msg, '', 'exam_jobs');

        $channel->close();
        $connection->close();

        return response()->json(['message' => 'Exam finished and job sent to queue']);
    }

    // POST /api/job-complete
    public function jobComplete(Request $request)
    {
        $validated = $request->validate([
            'exam_id' => 'required|integer',
            'message' => 'nullable|string'
        ]);

        // Log the notification message
        Log::info('Job complete notification received for exam_id: ' . $validated['exam_id'] .
                  ' Message: ' . ($validated['message'] ?? 'None'));

        return response()->json(['message' => 'Notification logged'], 200);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Exam extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'images'];

    protected $casts = [
        'images' => 'array',
    ];

    /**
     * Add an image to the exam.
     *
     * @param string $path
     * @return void
     */
    public function addImage(string $path)
    {
        $images = $this->images ?? [];
        $images[] = $path;
        $this->images = $images;
        $this->save();
    }

    /**
     * Check if the exam has enough images.
     *
     * @param int $minImages
     * @return bool
     */
    public function hasEnoughImages(int $minImages = 10): bool
    {
        return count($this->images ?? []) >= $minImages;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Arsip extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'nomor_surat',
        'nama_arsip',
        'nama_pewaris',
        'nik_pewaris',
        'files',
        'rw',
        'tgl_upload'
    ];

    protected $casts = [
        'files' => 'array',
        'tgl_upload' => 'date',
    ];

    /**
     * Konfigurasi Activity Log
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('arsip')
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $event) =>
                "Arsip {$this->nama_arsip} telah di {$event}"
            );
    }

    /**
     * Hapus file fisik saat arsip dihapus
     */
    protected static function booted()
    {
        static::deleting(function ($arsip) {
            if ($arsip->files) {
                foreach ($arsip->files as $file) {
                    Storage::disk('public')->delete($file);
                }
            }
        });
    }
    
}
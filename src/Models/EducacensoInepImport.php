<?php

namespace iEducar\Packages\Educacenso\Models;

use App\Models\Individual;
use iEducar\Packages\Educacenso\Enums\EducacensoImportStatus;
use iEducar\Packages\Educacenso\Services\EducacensoImportErrorMessage;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

class EducacensoInepImport extends Model
{
    protected $fillable = [
        'year',
        'school_name',
        'user_id',
        'status_id',
        'error_message',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $studentFileExport): void {
            $studentFileExport->status_id = EducacensoImportStatus::WAITING;
        });
    }

    public function user()
    {
        return $this->belongsTo(Individual::class, 'user_id', 'id');
    }

    protected function status(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->statusIsError() ? EducacensoImportStatus::ERROR->name() : EducacensoImportStatus::from($this->status_id)->name(),
        );
    }

    public function statusIsError(): bool
    {
        return $this->status_id === EducacensoImportStatus::ERROR->value || ($this->statusIsWaiting() && $this->created_at < now()->subMinutes(30));
    }

    public function statusIsWaiting(): bool
    {
        return $this->status_id === EducacensoImportStatus::WAITING->value;
    }

    public function markAsSuccess(?string $warningMessage = null): void
    {
        $this->updateStatus(EducacensoImportStatus::SUCCESS, $warningMessage);
    }

    public function markAsError(?string $errorMessage = null): void
    {
        $this->updateStatus(EducacensoImportStatus::ERROR, $errorMessage);
    }

    private function updateStatus(EducacensoImportStatus $status, ?string $errorMessage): void
    {
        try {
            $this->update([
                'status_id' => $status->value,
                'error_message' => $errorMessage,
            ]);
        } catch (QueryException $exception) {
            if (! str_contains($exception->getMessage(), 'error_message')) {
                throw $exception;
            }

            $this->update([
                'status_id' => $status->value,
            ]);
        }
    }

    protected function detail(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                if (filled($this->error_message)) {
                    return $this->error_message;
                }

                if ($this->statusIsWaiting() && $this->created_at < now()->subMinutes(30)) {
                    return EducacensoImportErrorMessage::TIMEOUT;
                }

                return null;
            }
        );
    }
}

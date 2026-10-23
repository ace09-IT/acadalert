<?php

namespace App\Repositories\Api;

use App\Services\Api\MockApiClient;
use Illuminate\Support\Collection;

class PaymentRepository
{
    public function __construct(
        protected MockApiClient $api,
        protected StudentRepository $students,
        protected AcademicStructureRepository $structure,
    ) {
    }

    public function all(): Collection
    {
        return $this->api->all('payments');
    }

    public function decorated(): Collection
    {
        $studentIndex = $this->students->keyedById();
        $blockIndex = $this->structure->blocks()->keyBy(fn ($row) => (int) $row->id);
        $yearLevelIndex = $this->structure->yearLevels()->keyBy(fn ($row) => (int) $row->id);
        $programIndex = $this->structure->programs()->keyBy(fn ($row) => (int) $row->id);
        $departmentIndex = $this->structure->departments()->keyBy(fn ($row) => (int) $row->id);

        return $this->all()
            ->map(function ($payment) use (
                $studentIndex,
                $blockIndex,
                $yearLevelIndex,
                $programIndex,
                $departmentIndex
            ) {
                $student = $studentIndex[(int) $payment->student_id] ?? null;

                $block = $student !== null ? ($blockIndex[(int) $student->block_id] ?? null) : null;
                $yearLevel = $block !== null ? ($yearLevelIndex[(int) $block->year_level_id] ?? null) : null;
                $program = $yearLevel !== null ? ($programIndex[(int) $yearLevel->program_id] ?? null) : null;
                $department = $program !== null ? ($departmentIndex[(int) $program->department_id] ?? null) : null;

                if ($student === null || $department === null) {
                    return null;
                }

                $row = clone $payment;
                $row->student_id = (int) $student->id;
                $row->first_name = $student->first_name;
                $row->last_name = $student->last_name;
                $row->student_number = $student->student_number;
                $row->student_email = $student->email;
                $row->program_code = $program->code ?? null;
                $row->program_name = $program->name ?? null;
                $row->department_code = $department->code ?? null;
                $row->department_name = $department->name ?? null;
                $row->block_name = $block->name ?? null;
                $row->year_level_name = $yearLevel->name ?? null;

                return $row;
            })
            ->filter()
            ->values();
    }

    /** One payment row by primary key, or null. */
    public function find(int $id): ?object
    {
        return $this->api->find('payments', $id);
    }

    public function decoratedById(int $id): ?object
    {
        $payment = $this->find($id);

        if ($payment === null) {
            return null;
        }

        $student = $this->students->find($payment->student_id);

        if ($student === null) {
            return null;
        }

        $placement = $this->structure->blockPlacement($student->block_id);
        $program = $placement !== null && $placement['program_id'] !== null
            ? $this->structure->programs()->firstWhere(fn ($row) => (int) $row->id === (int) $placement['program_id'])
            : null;

        $row = clone $payment;
        $row->first_name = $student->first_name;
        $row->last_name = $student->last_name;
        $row->student_number = $student->student_number;
        $row->program_code = $program->code ?? null;

        return $row;
    }

    public function history(): Collection
    {
        return $this->api->all('payment_history');
    }

    public function historyForPayment(int $paymentId): Collection
    {
        return $this->history()
            ->where('payment_id', $paymentId)
            ->sortByDesc(fn ($row) => (string) $row->created_at)
            ->values();
    }
}

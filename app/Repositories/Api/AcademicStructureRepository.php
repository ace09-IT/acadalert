<?php

namespace App\Repositories\Api;

use App\Services\Api\MockApiClient;
use Illuminate\Support\Collection;

class AcademicStructureRepository
{
    protected ?array $blocks = null;

    protected ?array $students = null;

    public function __construct(
        protected MockApiClient $api,
        protected StudentRepository $studentRepo,
    ) {
    }


    public function departments(): Collection
    {
        return $this->api->all('departments');
    }

    public function programs(): Collection
    {
        return $this->api->all('programs');
    }

    public function yearLevels(): Collection
    {
        return $this->api->all('year_levels');
    }

    public function blocks(): Collection
    {
        return $this->api->all('blocks');
    }

    public function department($id): ?object
    {
        return $this->api->find('departments', $id);
    }

    public function program($id): ?object
    {
        return $this->api->find('programs', $id);
    }

    public function block($id): ?object
    {
        return $this->api->find('blocks', $id);
    }

    public function programsForDepartment($departmentId): Collection
    {
        return $this->api->where('programs', ['department_id' => $departmentId])
            ->sortBy('name')
            ->values();
    }


    public function blockPlacements(): array
    {
        if ($this->blocks !== null) {
            return $this->blocks;
        }

        $programs = $this->programs()->keyBy(fn ($program) => (int) $program->id);
        $yearLevels = $this->yearLevels()->keyBy(fn ($year) => (int) $year->id);

        $index = [];

        foreach ($this->blocks() as $block) {
            $blockId = (int) $block->id;
            $year = $yearLevels[(int) $block->year_level_id] ?? null;
            $program = $year ? ($programs[(int) $year->program_id] ?? null) : null;

            $index[$blockId] = [
                'block_id' => $blockId,
                'block_name' => $block->name,
                'block_number' => $block->block_number,
                'year_level_id' => $year ? (int) $year->id : null,
                'year_number' => $year->year_number ?? null,
                'year_level_name' => $year->name ?? null,
                'program_id' => $program ? (int) $program->id : null,
                'program_code' => $program->code ?? null,
                'program_name' => $program->name ?? null,
                'department_id' => $program ? (int) $program->department_id : null,
            ];
        }

        return $this->blocks = $index;
    }

    public function blockPlacement($blockId): ?array
    {
        return $this->blockPlacements()[(int) $blockId] ?? null;
    }

    public function studentPlacements(): array
    {
        if ($this->students !== null) {
            return $this->students;
        }

        $blockPlacements = $this->blockPlacements();
        $index = [];

        foreach ($this->studentRepo->all() as $student) {
            $studentId = (int) $student->id;
            $placement = $blockPlacements[(int) $student->block_id] ?? null;

            if ($placement === null) {
                continue;
            }

            $index[$studentId] = [
                'student_id' => $studentId,
                'status' => (string) $student->status,
            ] + $placement;
        }

        return $this->students = $index;
    }


    public function studentIdsInDepartment($departmentId, bool $activeOnly = true): array
    {
        $target = (int) $departmentId;

        return $this->filterPlacements(
            fn (array $placement) => $placement['department_id'] === $target,
            $activeOnly
        );
    }

    public function studentIdsInProgram($programId, bool $activeOnly = true): array
    {
        $target = (int) $programId;

        return $this->filterPlacements(
            fn (array $placement) => $placement['program_id'] === $target,
            $activeOnly
        );
    }

    public function studentIdsInBlock($blockId, bool $activeOnly = true): array
    {
        $target = (int) $blockId;

        return $this->filterPlacements(
            fn (array $placement) => $placement['block_id'] === $target,
            $activeOnly
        );
    }

    public function studentIdsByProgramInDepartment($departmentId, bool $activeOnly = true): array
    {
        $target = (int) $departmentId;
        $grouped = [];

        foreach ($this->studentPlacements() as $studentId => $placement) {
            if ($placement['department_id'] !== $target) {
                continue;
            }

            if ($activeOnly && $placement['status'] !== 'Active') {
                continue;
            }

            if ($placement['program_id'] !== null) {
                $grouped[$placement['program_id']][] = $studentId;
            }
        }

        return $grouped;
    }

    protected function filterPlacements(callable $matches, bool $activeOnly): array
    {
        $ids = [];

        foreach ($this->studentPlacements() as $studentId => $placement) {
            if ($activeOnly && $placement['status'] !== 'Active') {
                continue;
            }

            if ($matches($placement)) {
                $ids[] = $studentId;
            }
        }

        return $ids;
    }


    public function isBlockInDepartment($blockId, $departmentId): bool
    {
        $placement = $this->blockPlacement($blockId);

        return $placement !== null
            && $placement['department_id'] !== null
            && $placement['department_id'] === (int) $departmentId;
    }

    public function isStudentInDepartment($studentId, $departmentId): bool
    {
        $placement = $this->studentPlacements()[(int) $studentId] ?? null;

        return $placement !== null
            && $placement['department_id'] !== null
            && $placement['department_id'] === (int) $departmentId;
    }

    public function isStudentPlaced($studentId): bool
    {
        return isset($this->studentPlacements()[(int) $studentId]);
    }

    public function departmentIdForStudent($studentId): ?int
    {
        return $this->studentPlacements()[(int) $studentId]['department_id'] ?? null;
    }

    public function programIdForBlock($blockId): ?int
    {
        return $this->blockPlacement($blockId)['program_id'] ?? null;
    }


    public function blocksWithProgramForDepartment($departmentId): Collection
    {
        $programs = $this->programsForDepartment($departmentId);
        $programIds = array_map('intval', $programs->pluck('id')->all());
        $programCodes = $programs->pluck('code', 'id')->all();

        $yearLevels = $this->yearLevels()->filter(
            fn ($year) => in_array((int) $year->program_id, $programIds, true)
        );

        $yearNumbers = $yearLevels->pluck('year_number', 'id')->all();
        $programIdByYear = $yearLevels->pluck('program_id', 'id')->all();

        return $this->blocks()
            ->filter(fn ($block) => isset($programIdByYear[(int) $block->year_level_id]))
            ->map(function ($block) use ($yearNumbers, $programIdByYear, $programCodes) {
                $decorated = clone $block;
                $yearLevelId = (int) $block->year_level_id;

                $decorated->year_number = $yearNumbers[$yearLevelId] ?? null;
                $decorated->program_code = $programCodes[(int) $programIdByYear[$yearLevelId]] ?? null;

                return $decorated;
            })
            ->sortBy([
                ['program_code', 'asc'],
                ['year_number', 'asc'],
                ['block_number', 'asc'],
            ])
            ->values();
    }

    public function blocksForProgram($programId): Collection
    {
        $target = (int) $programId;
        $placements = $this->blockPlacements();

        return $this->blocks()
            ->filter(function ($block) use ($target, $placements) {
                $placement = $placements[(int) $block->id] ?? null;

                return $placement !== null && $placement['program_id'] === $target;
            })
            ->map(function ($block) use ($placements) {
                $placement = $placements[(int) $block->id];

                $decorated = clone $block;
                $decorated->year_number = $placement['year_number'];
                $decorated->year_level_name = $placement['year_level_name'];

                return $decorated;
            })
            ->sortBy([
                ['year_number', 'asc'],
                ['block_number', 'asc'],
            ])
            ->values();
    }

    public function blockWithProgramDetail($blockId): ?object
    {
        $block = $this->block($blockId);
        $placement = $block !== null ? $this->blockPlacement($blockId) : null;

        if ($block === null || $placement === null || $placement['program_id'] === null) {
            return null;
        }

        $program = $this->program($placement['program_id']);

        $decorated = clone $block;
        $decorated->year_number = $placement['year_number'];
        $decorated->year_level_name = $placement['year_level_name'];
        $decorated->program_code = $placement['program_code'];
        $decorated->program_name = $program->name ?? null;
        $decorated->program_id = $placement['program_id'];

        return $decorated;
    }

    public function blockWithProgram($blockId): ?object
    {
        $block = $this->block($blockId);

        if ($block === null) {
            return null;
        }

        $placement = $this->blockPlacement($blockId);
        $program = ($placement !== null && $placement['program_id'] !== null)
            ? $this->program($placement['program_id'])
            : null;

        $decorated = clone $block;
        $decorated->year_number = $placement['year_number'] ?? null;
        $decorated->program_code = $placement['program_code'] ?? null;
        $decorated->program_name = $program->name ?? null;

        return $decorated;
    }
}

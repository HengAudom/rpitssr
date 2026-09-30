<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\Student;
use App\Models\StudentSubmission;
use App\Models\SubmissionDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResultController extends Controller
{
    /**
     * Student's own submission history (Without score/answers).
     */
    public function studentResults(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $student = ($user instanceof Student) 
            ? $user 
            : (Student::find($user->StudentId ?? $user->id) ?? Student::where('UserId', $user->id)->first());
        if (! $student) {
            return response()->json(['results' => []]);
        }

        $submissions = DB::table('tblstudentsubmission as ss')
            ->join('tbltest as t', 'ss.TestId', '=', 't.TestId')
            ->where('ss.StudentId', $student->StudentId)
            ->whereNotNull('ss.CompletedAt')
            ->select(
                'ss.SubmissionId as id',
                't.TestId as testId',
                't.TestName as testName',
                't.AcademicYear as academicYear',
                't.ExamDay as examDay',
                DB::raw('COALESCE(ss.TotalMarks, t.TotalMarks) as totalMarks'),
                't.DurationMinutes as durationMinutes',
                'ss.StartedAt as startedAt',
                'ss.CompletedAt as completedAt',
                DB::raw("'Submitted' as status")
            )
            ->orderBy('ss.CompletedAt', 'desc')
            ->get();

        return response()->json(['results' => $submissions]);
    }

    /**
     * Detailed result for one submission (Admin/Super Admin only).
     */
    public function submissionDetail(Request $request, $submissionId)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Student is strictly prohibited from retrieving score details
        if ($user->role === 'Student') {
            return response()->json([
                'success' => false,
                'message' => 'លទ្ធផលប្រឡងមិនទាន់អាចបង្ហាញបានទេ (Exam results are not accessible for students).'
            ], 403);
        }

        $submission = StudentSubmission::with(['student', 'test.questions.answers', 'details.question', 'details.selectedAnswer'])
            ->find($submissionId);

        if (! $submission) {
            return response()->json(['message' => 'Submission not found.'], 404);
        }

        $test = $submission->test;
        $allQuestions = $test ? $test->questions : collect();

        // If this submission had locked assigned questions (e.g. 50 out of 100)
        $assignedIds = !empty($submission->AssignedQuestionIds)
            ? (is_array($submission->AssignedQuestionIds) ? $submission->AssignedQuestionIds : json_decode($submission->AssignedQuestionIds, true))
            : null;

        if (!empty($assignedIds) && is_array($assignedIds)) {
            $questionMap = $allQuestions->keyBy('QuestionId');
            $ordered = collect();
            foreach ($assignedIds as $qId) {
                if (isset($questionMap[$qId])) {
                    $ordered->push($questionMap[$qId]);
                }
            }
            if ($ordered->isNotEmpty()) {
                $allQuestions = $ordered;
            }
        }

        $totalQuestions = $allQuestions->count();
        $correct = $submission->TotalCorrect ?? 0;
        
        // Use the loaded details collection to avoid redundant DB queries
        $details = $submission->details;

        // Elapsed time in minutes
        $startedAt    = $submission->StartedAt ? \Carbon\Carbon::parse($submission->StartedAt) : null;
        $completedAt  = $submission->CompletedAt ? \Carbon\Carbon::parse($submission->CompletedAt) : null;
        $elapsedMin   = ($startedAt && $completedAt)
            ? (int) max(0, round(($completedAt->getTimestamp() - $startedAt->getTimestamp()) / 60))
            : 0;

        $accuracy = $totalQuestions > 0
            ? round(($correct / $totalQuestions) * 100, 1)
            : 0;

        $questionsData = $allQuestions->map(function ($q) use ($details) {
            $detail = $details->firstWhere('QuestionId', $q->QuestionId);

            $answers = $q->answers->map(fn($a) => [
                'id'        => $a->AnswerId,
                'text'      => $a->AnswerText,
                'isCorrect' => (bool) $a->IsCorrect,
            ]);

            return [
                'id'             => $q->QuestionId,
                'text'           => $q->QuestionText,
                'passage'        => $q->Passage,
                'answers'        => $answers,
                'selectedId'     => $detail?->SelectedAnswerId,
                'selectedText'   => $detail?->selectedAnswer?->AnswerText,
                'isCorrect'      => (bool) ($detail?->IsCorrect ?? false),
                'skipped'        => $detail === null || is_null($detail->SelectedAnswerId),
            ];
        });

        $incorrect = max(0, $totalQuestions - $correct);

        if ($submission->TotalMarks !== null && (float)$submission->TotalMarks > 0) {
            $effectiveTotalMarks = (float) $submission->TotalMarks;
        } else {
            $testTotalMarks = (float) ($test?->TotalMarks ?: 100);
            $sumActivePts = (float) $allQuestions->sum('Points');
            $bankCount = $test ? $test->questions()->count() : 0;

            if ($test && $test->QuestionLimit && (int)$test->QuestionLimit > 0 && $bankCount > 0 && (int)$test->QuestionLimit < $bankCount) {
                $effectiveTotalMarks = $sumActivePts > 0 ? $sumActivePts : round(((int)$test->QuestionLimit / $bankCount) * $testTotalMarks, 2);
            } else {
                $effectiveTotalMarks = $sumActivePts > 0 ? $sumActivePts : $testTotalMarks;
            }
        }

        $rawScore = (float) ($submission->Score ?? 0);
        if ($totalQuestions > 0 && $rawScore > $effectiveTotalMarks) {
            $score = round(($correct / $totalQuestions) * $effectiveTotalMarks, 2);
        } else {
            $score = $rawScore;
        }

        $accuracy = $totalQuestions > 0
            ? min(100.0, round(($correct / $totalQuestions) * 100, 1))
            : 0;

        return response()->json([
            'submissionId'   => $submission->SubmissionId,
            'studentId'      => $submission->student ? ($submission->student->StudentCode ?? (string)$submission->student->StudentId) : 'N/A',
            'studentName'    => $submission->student ? ($submission->student->FirstName . ' ' . $submission->student->LastName) : 'N/A',
            'testName'       => $test?->TestName,
            'totalMarks'     => $effectiveTotalMarks,
            'score'          => $score,
            'totalCorrect'   => $correct,
            'totalQuestions' => $totalQuestions,
            'incorrect'      => $incorrect,
            'interruptions'  => (int) ($submission->Interruptions ?? 0),
            'accuracy'       => $accuracy,
            'elapsedMinutes' => $elapsedMin,
            'completedAt'    => $completedAt ? $completedAt->toIso8601String() : null,
            'questions'      => $questionsData,
        ]);
    }
}

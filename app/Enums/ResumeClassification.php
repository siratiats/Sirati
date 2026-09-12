<?php

namespace App\Enums;

enum ResumeClassification: string
{
    case Resume = 'resume';
    case NotResume = 'not_resume';
    case Uncertain = 'uncertain';
}

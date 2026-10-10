{{-- "Scenario · what was observed" for a run ($run). Allowed without an
     exposure can only be the owner reading their own resource, which reads
     better spelled out than as a bare "allowed" next to PROTECTED. --}}
@use('App\Domain\Security\Enums\SecurityTestObservedOutcome')
@use('App\Domain\Security\Enums\SecurityTestVerdict')

@php
    $ownerAccess = $run->security_verdict === SecurityTestVerdict::Protected
        && $run->observed_outcome === SecurityTestObservedOutcome::Allowed;
@endphp

{{ $run->scenario->label() }} · {{ $ownerAccess ? 'acesso legítimo do dono' : ($run->observed_outcome?->label() ?? 'erro na execução') }}

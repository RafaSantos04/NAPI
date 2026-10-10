{{-- "Scenario · what was observed" for a Mass Assignment run ($run). A
     payload applied in full without an exposure can only be one that carried
     no protected property, which reads better spelled out than as a bare
     "allowed" next to PROTECTED. --}}
@use('App\Domain\Security\Enums\SecurityTestObservedOutcome')
@use('App\Domain\Security\Enums\SecurityTestVerdict')

{{ $run->scenario->label() }} · {{ match (true) {
    $run->security_verdict === SecurityTestVerdict::Exposed => 'propriedade protegida alterada',
    $run->security_verdict === SecurityTestVerdict::Inconclusive => 'erro na execução',
    $run->observed_outcome === SecurityTestObservedOutcome::Denied => 'propriedade protegida ignorada',
    default => 'alteração legítima',
} }}

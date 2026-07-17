<?php

declare(strict_types=1);

namespace WebFunction;

/**
 * A pipeline batches several Web Function calls into a single HTTP request, executed by the server in
 * order. Steps can reference earlier results via {@see Promise}, cutting the number of round trips.
 *
 * @example
 *   $pipeline = new Pipeline("https://pipe.example/exec");
 *   $pipeline->addStep(["url" => "https://a", "headers" => [], "body" => []]);
 *   $pipeline->addStep(["url" => "https://b", "headers" => [], "body" => []]);
 *   $pipeline->execute('all'); // => [["a" => 1], ["b" => 2]]
 */
final class Pipeline
{
    /** @var array<int, array{url: string, headers: array<string, string>, body: array<string, mixed>}> */
    private $steps = [];

    /** @var array<int, Promise> */
    private $promises = [];

    /** @var string */
    private $url;

    public function __construct(string $url)
    {
        $this->url = $url;
    }

    /**
     * Adds a step to the pipeline.
     *
     * @param array{url: string, headers: array<string, string>, body: array<string, mixed>} $step
     */
    public function addStep(array $step): Promise
    {
        $n = count($this->promises);
        $promise = new Promise($this, "\$[{$n}]");

        $this->steps[] = $step;
        $this->promises[] = $promise;

        return $promise;
    }

    /**
     * Executes the pipeline.
     *
     * @param string $returns `'all'` (default) returns every step's result as an array, `'last'` returns
     *   only the final step's result, and any other string is treated as a JSONPath expression to extract
     *   a specific value.
     * @return mixed
     */
    public function execute(string $returns = 'all')
    {
        switch ($returns) {
            case 'all':
                return $this->executeAll();
            case 'last':
                return $this->executeLast();
            default:
                return $this->executeCustom($returns);
        }
    }

    /**
     * Resets the pipeline, discarding any pending (not-yet-executed) steps and promises.
     */
    public function reset(): void
    {
        $this->steps = [];
        $this->promises = [];
    }

    /**
     * @return mixed
     */
    private function executeAll()
    {
        $responses = Request::execute($this->url, null, null, [
            'steps' => $this->steps,
            'returns' => '$',
        ]);

        if (is_array($responses)) {
            foreach ($responses as $index => $response) {
                if (isset($this->promises[$index])) {
                    $this->promises[$index]->setValue($response);
                }
            }
        }

        $this->reset();

        return $responses;
    }

    /**
     * @return mixed
     */
    private function executeLast()
    {
        $response = Request::execute($this->url, null, null, [
            'steps' => $this->steps,
            'returns' => '$[-1:]',
        ]);

        if (!empty($this->promises)) {
            $this->promises[array_key_last($this->promises)]->setValue($response);
        }

        $this->reset();

        return $response;
    }

    /**
     * @return mixed
     */
    private function executeCustom(string $returns)
    {
        $response = Request::execute($this->url, null, null, [
            'steps' => $this->steps,
            'returns' => $returns,
        ]);

        $this->reset();

        return $response;
    }
}

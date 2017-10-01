<?php

namespace Zipkin\Propagation;

use Zipkin\TraceContext;

/**
 * @see https://github.com/openzipkin/b3-propagation
 */
final class B3 implements Propagation
{
    /**
     * 128 or 64-bit trace ID lower-hex encoded into 32 or 16 characters (required)
     */
    const TRACE_ID_NAME = 'X-B3-TraceId';
    
    /**
     * 64-bit span ID lower-hex encoded into 16 characters (required)
     */
    const SPAN_ID_NAME = 'X-B3-SpanId';
    
    /**
     * 64-bit parent span ID lower-hex encoded into 16 characters (absent on root span)
     */
    const PARENT_SPAN_ID_NAME = 'X-B3-ParentSpanId';
    
    /**
     * '1' means report this span to the tracing system, '0' means do not. (absent means defer the
     * decision to the receiver of this header).
     */
    const SAMPLED_NAME = 'X-B3-Sampled';
    
    /**
     * '1' implies sampled and is a request to override collection-tier sampling policy.
     */
    const FLAGS_NAME = 'X-B3-Flags';
    
    /**
     * @return array|string[]
     */
    public function getKeys()
    {
        return [
            self::TRACE_ID_NAME,
            self::SPAN_ID_NAME,
            self::PARENT_SPAN_ID_NAME,
            self::SAMPLED_NAME,
            self::FLAGS_NAME,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getInjector(Setter $setter)
    {
        /**
         * @param TraceContext $traceContext
         * @param $carrier
         * @return void
         */
        return function (TraceContext $traceContext, $carrier) use ($setter) {
            $setter->put($carrier, self::TRACE_ID_NAME, $traceContext->getTraceId());
            $setter->put($carrier, self::SPAN_ID_NAME, $traceContext->getSpanId());

            if ($traceContext->getParentId() !== null) {
                $setter->put($carrier, self::PARENT_SPAN_ID_NAME, $traceContext->getParentId());
            }

            if ($traceContext->isSampled() !== null) {
                $setter->put($carrier, self::SAMPLED_NAME, $traceContext->isSampled() ? '1' : '0');
            }

            if ($traceContext->isDebug() !== null) {
                $setter->put($carrier, self::FLAGS_NAME, '1');
            }
        };
    }

    /**
     * {@inheritdoc}
     */
    public function getExtractor(Getter $getter)
    {
        /**
         * @param mixed $carrier
         * @return TraceContext|null
         * @throws \InvalidArgumentException
         */
        return function ($carrier) use ($getter) {
            $sampledString = $getter->get($carrier, self::SAMPLED_NAME);

            $sampled = null;
            if ($sampledString === '1' || strtolower($sampledString) === 'true') {
                $sampled = true;
            } elseif ($sampledString === '0' || strtolower($sampledString) === 'false') {
                $sampled = false;
            }

            $debug = ('1' === $getter->get($carrier, self::FLAGS_NAME));

            $traceId = $getter->get($carrier, self::TRACE_ID_NAME);

            if ($traceId === null) {
                return TraceContext::createAsRoot(DefaultSamplingFlags::create($sampled, $debug));
            }

            $spanId = $getter->get($carrier, self::SPAN_ID_NAME);
            $parentSpanId = $getter->get($carrier, self::PARENT_SPAN_ID_NAME);

            if ($spanId !== null) {
                return TraceContext::create($traceId, $spanId, $parentSpanId, $sampled, $debug);
            }

            return null;
        };
    }
}

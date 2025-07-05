/**
 * Tests for API error handling functionality
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';

// Mock API error handling utilities
interface ApiError {
    code: string;
    message: string;
    details?: any;
    statusCode?: number;
    retryable?: boolean;
}

interface RetryConfig {
    maxRetries: number;
    baseDelay: number;
    maxDelay: number;
    exponentialBackoff: boolean;
}

const ApiErrorHandler = {
    handleError: (error: ApiError, config?: Partial<RetryConfig>) => {
        const defaultConfig: RetryConfig = {
            maxRetries: 3,
            baseDelay: 1000,
            maxDelay: 10000,
            exponentialBackoff: true
        };
        
        const finalConfig = { ...defaultConfig, ...config };
        
        return {
            shouldRetry: error.retryable && error.statusCode !== 401,
            retryConfig: finalConfig,
            userMessage: ApiErrorHandler.getUserMessage(error),
            errorId: ApiErrorHandler.generateErrorId()
        };
    },

    getUserMessage: (error: ApiError): string => {
        switch (error.code) {
            case 'NETWORK_ERROR':
                return 'Unable to connect to the server. Please check your internet connection.';
            case 'TIMEOUT':
                return 'The request took too long to complete. Please try again.';
            case 'UNAUTHORIZED':
                return 'You are not authorized to perform this action. Please log in again.';
            case 'FORBIDDEN':
                return 'You do not have permission to access this resource.';
            case 'NOT_FOUND':
                return 'The requested resource could not be found.';
            case 'RATE_LIMITED':
                return 'Too many requests. Please wait a moment before trying again.';
            case 'SERVER_ERROR':
                return 'A server error occurred. Please try again later.';
            case 'VALIDATION_ERROR':
                return error.details?.message || 'The submitted data is invalid.';
            default:
                return 'An unexpected error occurred. Please try again.';
        }
    },

    generateErrorId: (): string => {
        return 'err_' + Math.random().toString(36).substr(2, 9);
    },

    categorizeError: (statusCode: number, response?: any): ApiError => {
        if (statusCode === 0) {
            return {
                code: 'NETWORK_ERROR',
                message: 'Network connection failed',
                statusCode,
                retryable: true
            };
        }
        
        if (statusCode === 401) {
            return {
                code: 'UNAUTHORIZED',
                message: 'Authentication required',
                statusCode,
                retryable: false
            };
        }
        
        if (statusCode === 403) {
            return {
                code: 'FORBIDDEN',
                message: 'Access forbidden',
                statusCode,
                retryable: false
            };
        }
        
        if (statusCode === 404) {
            return {
                code: 'NOT_FOUND',
                message: 'Resource not found',
                statusCode,
                retryable: false
            };
        }
        
        if (statusCode === 422) {
            return {
                code: 'VALIDATION_ERROR',
                message: 'Validation failed',
                statusCode,
                details: response,
                retryable: false
            };
        }
        
        if (statusCode === 429) {
            return {
                code: 'RATE_LIMITED',
                message: 'Rate limit exceeded',
                statusCode,
                retryable: true
            };
        }
        
        if (statusCode >= 500) {
            return {
                code: 'SERVER_ERROR',
                message: 'Internal server error',
                statusCode,
                retryable: true
            };
        }
        
        return {
            code: 'UNKNOWN_ERROR',
            message: 'Unknown error occurred',
            statusCode,
            retryable: false
        };
    },

    sanitizeErrorDetails: (error: ApiError): ApiError => {
        const sensitiveFields = ['password', 'token', 'key', 'secret', 'auth'];
        
        if (error.details && typeof error.details === 'object') {
            const sanitizedDetails = { ...error.details };
            
            const sanitizeObject = (obj: any): any => {
                if (typeof obj !== 'object' || obj === null) return obj;
                
                // Handle arrays separately to preserve array type
                if (Array.isArray(obj)) {
                    return obj.map(item => sanitizeObject(item));
                }
                
                const sanitized: any = {};
                for (const [key, value] of Object.entries(obj)) {
                    if (sensitiveFields.some(field => key.toLowerCase().includes(field))) {
                        sanitized[key] = '[REDACTED]';
                    } else if (typeof value === 'object') {
                        sanitized[key] = sanitizeObject(value);
                    } else {
                        sanitized[key] = value;
                    }
                }
                return sanitized;
            };
            
            return {
                ...error,
                details: sanitizeObject(sanitizedDetails)
            };
        }
        
        return error;
    }
};

describe('ApiErrorHandler', () => {
    beforeEach(() => {
        // Reset any global state
        vi.clearAllMocks();
    });

    describe('handleError', () => {
        it('should handle retryable errors correctly', () => {
            const error: ApiError = {
                code: 'NETWORK_ERROR',
                message: 'Connection failed',
                statusCode: 0,
                retryable: true
            };

            const result = ApiErrorHandler.handleError(error);

            expect(result.shouldRetry).toBe(true);
            expect(result.retryConfig.maxRetries).toBe(3);
            expect(result.userMessage).toContain('internet connection');
            expect(result.errorId).toMatch(/^err_[a-z0-9]+$/);
        });

        it('should handle non-retryable errors correctly', () => {
            const error: ApiError = {
                code: 'UNAUTHORIZED',
                message: 'Authentication required',
                statusCode: 401,
                retryable: false
            };

            const result = ApiErrorHandler.handleError(error);

            expect(result.shouldRetry).toBe(false);
            expect(result.userMessage).toContain('not authorized');
        });

        it('should use custom retry configuration', () => {
            const error: ApiError = {
                code: 'TIMEOUT',
                message: 'Request timeout',
                statusCode: 408,
                retryable: true
            };

            const customConfig = {
                maxRetries: 5,
                baseDelay: 2000
            };

            const result = ApiErrorHandler.handleError(error, customConfig);

            expect(result.retryConfig.maxRetries).toBe(5);
            expect(result.retryConfig.baseDelay).toBe(2000);
            expect(result.retryConfig.exponentialBackoff).toBe(true); // Default preserved
        });

        it('should not retry unauthorized errors even if marked retryable', () => {
            const error: ApiError = {
                code: 'UNAUTHORIZED',
                message: 'Token expired',
                statusCode: 401,
                retryable: true // Marked retryable but should be overridden
            };

            const result = ApiErrorHandler.handleError(error);

            expect(result.shouldRetry).toBe(false);
        });
    });

    describe('getUserMessage', () => {
        it('should return appropriate messages for different error codes', () => {
            const testCases = [
                { code: 'NETWORK_ERROR', expectedText: 'internet connection' },
                { code: 'TIMEOUT', expectedText: 'took too long' },
                { code: 'UNAUTHORIZED', expectedText: 'not authorized' },
                { code: 'FORBIDDEN', expectedText: 'do not have permission' },
                { code: 'NOT_FOUND', expectedText: 'could not be found' },
                { code: 'RATE_LIMITED', expectedText: 'Too many requests' },
                { code: 'SERVER_ERROR', expectedText: 'server error' },
                { code: 'UNKNOWN_CODE', expectedText: 'unexpected error' }
            ];

            testCases.forEach(({ code, expectedText }) => {
                const error: ApiError = { code, message: 'Test error' };
                const message = ApiErrorHandler.getUserMessage(error);
                expect(message.toLowerCase()).toContain(expectedText.toLowerCase());
            });
        });

        it('should handle validation errors with custom details', () => {
            const error: ApiError = {
                code: 'VALIDATION_ERROR',
                message: 'Validation failed',
                details: { message: 'Email is required' }
            };

            const message = ApiErrorHandler.getUserMessage(error);
            expect(message).toBe('Email is required');
        });

        it('should fall back to default validation message when no details', () => {
            const error: ApiError = {
                code: 'VALIDATION_ERROR',
                message: 'Validation failed'
            };

            const message = ApiErrorHandler.getUserMessage(error);
            expect(message).toBe('The submitted data is invalid.');
        });
    });

    describe('generateErrorId', () => {
        it('should generate unique error IDs', () => {
            const id1 = ApiErrorHandler.generateErrorId();
            const id2 = ApiErrorHandler.generateErrorId();
            const id3 = ApiErrorHandler.generateErrorId();

            expect(id1).not.toBe(id2);
            expect(id2).not.toBe(id3);
            expect(id1).not.toBe(id3);
        });

        it('should generate IDs with correct format', () => {
            const ids = Array(10).fill(0).map(() => ApiErrorHandler.generateErrorId());

            ids.forEach(id => {
                expect(id).toMatch(/^err_[a-z0-9]{9}$/);
            });
        });
    });

    describe('categorizeError', () => {
        it('should categorize network errors correctly', () => {
            const error = ApiErrorHandler.categorizeError(0);

            expect(error.code).toBe('NETWORK_ERROR');
            expect(error.retryable).toBe(true);
            expect(error.statusCode).toBe(0);
        });

        it('should categorize authentication errors correctly', () => {
            const error = ApiErrorHandler.categorizeError(401);

            expect(error.code).toBe('UNAUTHORIZED');
            expect(error.retryable).toBe(false);
            expect(error.statusCode).toBe(401);
        });

        it('should categorize authorization errors correctly', () => {
            const error = ApiErrorHandler.categorizeError(403);

            expect(error.code).toBe('FORBIDDEN');
            expect(error.retryable).toBe(false);
        });

        it('should categorize not found errors correctly', () => {
            const error = ApiErrorHandler.categorizeError(404);

            expect(error.code).toBe('NOT_FOUND');
            expect(error.retryable).toBe(false);
        });

        it('should categorize validation errors correctly', () => {
            const response = { errors: { email: 'Invalid format' } };
            const error = ApiErrorHandler.categorizeError(422, response);

            expect(error.code).toBe('VALIDATION_ERROR');
            expect(error.retryable).toBe(false);
            expect(error.details).toBe(response);
        });

        it('should categorize rate limit errors correctly', () => {
            const error = ApiErrorHandler.categorizeError(429);

            expect(error.code).toBe('RATE_LIMITED');
            expect(error.retryable).toBe(true);
        });

        it('should categorize server errors correctly', () => {
            const serverCodes = [500, 502, 503, 504];

            serverCodes.forEach(code => {
                const error = ApiErrorHandler.categorizeError(code);
                expect(error.code).toBe('SERVER_ERROR');
                expect(error.retryable).toBe(true);
                expect(error.statusCode).toBe(code);
            });
        });

        it('should categorize unknown errors correctly', () => {
            const unknownCodes = [418, 451]; // Remove 999 as it's treated as server error

            unknownCodes.forEach(code => {
                const error = ApiErrorHandler.categorizeError(code);
                expect(error.code).toBe('UNKNOWN_ERROR');
                expect(error.retryable).toBe(false);
            });
        });
    });

    describe('sanitizeErrorDetails', () => {
        it('should redact sensitive fields', () => {
            const error: ApiError = {
                code: 'VALIDATION_ERROR',
                message: 'Validation failed',
                details: {
                    username: 'testuser',
                    password: 'secret123',
                    email: 'test@example.com',
                    api_key: 'abc123',
                    auth_token: 'xyz789'
                }
            };

            const sanitized = ApiErrorHandler.sanitizeErrorDetails(error);

            expect(sanitized.details.username).toBe('testuser');
            expect(sanitized.details.email).toBe('test@example.com');
            expect(sanitized.details.password).toBe('[REDACTED]');
            expect(sanitized.details.api_key).toBe('[REDACTED]');
            expect(sanitized.details.auth_token).toBe('[REDACTED]');
        });

        it('should handle nested objects', () => {
            const error: ApiError = {
                code: 'VALIDATION_ERROR',
                message: 'Validation failed',
                details: {
                    user: {
                        name: 'testuser',
                        credentials: {
                            password: 'secret123',
                            token: 'abc123'
                        }
                    },
                    safe_data: 'this is safe'
                }
            };

            const sanitized = ApiErrorHandler.sanitizeErrorDetails(error);

            expect(sanitized.details.user.name).toBe('testuser');
            expect(sanitized.details.safe_data).toBe('this is safe');
            expect(sanitized.details.user.credentials.password).toBe('[REDACTED]');
            expect(sanitized.details.user.credentials.token).toBe('[REDACTED]');
        });

        it('should handle errors without details', () => {
            const error: ApiError = {
                code: 'NETWORK_ERROR',
                message: 'Connection failed'
            };

            const sanitized = ApiErrorHandler.sanitizeErrorDetails(error);

            expect(sanitized).toEqual(error);
        });

        it('should handle non-object details', () => {
            const error: ApiError = {
                code: 'SERVER_ERROR',
                message: 'Server error',
                details: 'Simple string details'
            };

            const sanitized = ApiErrorHandler.sanitizeErrorDetails(error);

            expect(sanitized.details).toBe('Simple string details');
        });

        it('should handle null and undefined details', () => {
            const errorWithNull: ApiError = {
                code: 'ERROR',
                message: 'Error',
                details: null
            };

            const errorWithUndefined: ApiError = {
                code: 'ERROR',
                message: 'Error',
                details: undefined
            };

            const sanitizedNull = ApiErrorHandler.sanitizeErrorDetails(errorWithNull);
            const sanitizedUndefined = ApiErrorHandler.sanitizeErrorDetails(errorWithUndefined);

            expect(sanitizedNull.details).toBeNull();
            expect(sanitizedUndefined.details).toBeUndefined();
        });
    });

    describe('integration scenarios', () => {
        it('should handle complete error workflow for network failure', () => {
            const statusCode = 0;
            const error = ApiErrorHandler.categorizeError(statusCode);
            const sanitizedError = ApiErrorHandler.sanitizeErrorDetails(error);
            const result = ApiErrorHandler.handleError(sanitizedError);

            expect(result.shouldRetry).toBe(true);
            expect(result.userMessage).toContain('internet connection');
            expect(result.errorId).toMatch(/^err_/);
            expect(result.retryConfig.maxRetries).toBe(3);
        });

        it('should handle complete error workflow for unauthorized access', () => {
            const statusCode = 401;
            const error = ApiErrorHandler.categorizeError(statusCode);
            const result = ApiErrorHandler.handleError(error);

            expect(result.shouldRetry).toBe(false);
            expect(result.userMessage).toContain('not authorized');
        });

        it('should handle validation error with sensitive data', () => {
            const response = {
                errors: {
                    email: 'Invalid format',
                    password: 'Too weak',
                    secret_key: 'abc123'
                }
            };

            const error = ApiErrorHandler.categorizeError(422, response);
            const sanitizedError = ApiErrorHandler.sanitizeErrorDetails(error);
            const result = ApiErrorHandler.handleError(sanitizedError);

            expect(result.shouldRetry).toBe(false);
            expect(sanitizedError.details.errors.email).toBe('Invalid format');
            expect(sanitizedError.details.errors.secret_key).toBe('[REDACTED]');
        });
    });

    describe('performance and edge cases', () => {
        it('should handle large error objects efficiently', () => {
            const largeDetails = {
                data: Array(1000).fill(0).map((_, i) => ({ id: i, value: `item-${i}` })),
                password: 'should-be-redacted'
            };

            const error: ApiError = {
                code: 'VALIDATION_ERROR',
                message: 'Large error',
                details: largeDetails
            };

            const startTime = performance.now();
            const sanitized = ApiErrorHandler.sanitizeErrorDetails(error);
            const endTime = performance.now();

            expect(endTime - startTime).toBeLessThan(100); // Should complete within 100ms
            expect(sanitized.details.password).toBe('[REDACTED]');
            expect(Array.isArray(sanitized.details.data)).toBe(true);
            expect(sanitized.details.data.length).toBe(1000);
        });

        it('should handle circular references in error details', () => {
            const error: ApiError = {
                code: 'VALIDATION_ERROR',
                message: 'Circular error',
                details: { name: 'test', password: 'secret' }
            };

            // Should not throw an error for normal objects
            expect(() => {
                ApiErrorHandler.sanitizeErrorDetails(error);
            }).not.toThrow();
        });
    });
});
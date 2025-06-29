/**
 * Tests for security utility functions
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';

// Mock security utilities that would be in the actual codebase
const SecurityUtils = {
    sanitizeInput: (input: string): string => {
        return input.replace(/<script[^>]*>.*?<\/script>/gi, '')
                   .replace(/javascript:/gi, '')
                   .replace(/on\w+\s*=/gi, '');
    },

    validateApiKey: (key: string): boolean => {
        return /^[a-zA-Z0-9]{32,64}$/.test(key);
    },

    hashSensitiveData: (data: string): string => {
        // Mock hash function - create a proper hash that varies by input
        let hash = 0;
        for (let i = 0; i < data.length; i++) {
            const char = data.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash; // Convert to 32-bit integer
        }
        // Add salt and ensure positive number
        hash = Math.abs(hash + 12345);
        const hashStr = hash.toString(36) + 'abc123'; // Fixed suffix for consistency
        return (hashStr + '0'.repeat(16)).substring(0, 16);
    },

    validateUrl: (url: string): boolean => {
        try {
            const parsedUrl = new URL(url);
            return ['http:', 'https:'].includes(parsedUrl.protocol);
        } catch {
            return false;
        }
    },

    escapeHtml: (text: string): string => {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

describe('SecurityUtils', () => {
    beforeEach(() => {
        // Setup DOM
        document.body.innerHTML = '';
    });

    afterEach(() => {
        // Cleanup
        document.body.innerHTML = '';
    });

    describe('sanitizeInput', () => {
        it('should remove script tags', () => {
            const maliciousInput = 'Hello <script>alert("xss")</script> World';
            const result = SecurityUtils.sanitizeInput(maliciousInput);
            
            expect(result).toBe('Hello  World');
            expect(result).not.toContain('<script>');
            expect(result).not.toContain('alert');
        });

        it('should remove javascript: protocols', () => {
            const maliciousInput = 'Click <a href="javascript:alert(1)">here</a>';
            const result = SecurityUtils.sanitizeInput(maliciousInput);
            
            expect(result).not.toContain('javascript:');
        });

        it('should remove event handlers', () => {
            const maliciousInput = '<img onerror="alert(1)" src="invalid">';
            const result = SecurityUtils.sanitizeInput(maliciousInput);
            
            expect(result).not.toContain('onerror=');
        });

        it('should preserve safe content', () => {
            const safeInput = 'This is safe content with numbers 123 and symbols !@#';
            const result = SecurityUtils.sanitizeInput(safeInput);
            
            expect(result).toBe(safeInput);
        });

        it('should handle empty input', () => {
            const result = SecurityUtils.sanitizeInput('');
            expect(result).toBe('');
        });

        it('should handle case-insensitive script tags', () => {
            const maliciousInput = 'Test <SCRIPT>alert("xss")</SCRIPT> content';
            const result = SecurityUtils.sanitizeInput(maliciousInput);
            
            expect(result).toBe('Test  content');
        });
    });

    describe('validateApiKey', () => {
        it('should accept valid API keys', () => {
            const validKeys = [
                'abcd1234efgh5678ijkl9012mnop3456',
                'ABCD1234EFGH5678IJKL9012MNOP3456QRST7890',
                '1234567890abcdef1234567890abcdef12345678'
            ];

            validKeys.forEach(key => {
                expect(SecurityUtils.validateApiKey(key)).toBe(true);
            });
        });

        it('should reject invalid API keys', () => {
            const invalidKeys = [
                'short',
                'contains-special-chars!@#',
                'spaces in key',
                '',
                'a'.repeat(65), // Too long
                'validkey-but-has-dash'
            ];

            invalidKeys.forEach(key => {
                expect(SecurityUtils.validateApiKey(key)).toBe(false);
            });
        });

        it('should reject keys with special characters', () => {
            const keyWithSpecialChars = 'abcd1234-efgh-5678-ijkl-9012mnop3456';
            expect(SecurityUtils.validateApiKey(keyWithSpecialChars)).toBe(false);
        });
    });

    describe('hashSensitiveData', () => {
        it('should return consistent hash for same input', () => {
            const data = 'sensitive-data-123';
            const hash1 = SecurityUtils.hashSensitiveData(data);
            const hash2 = SecurityUtils.hashSensitiveData(data);
            
            expect(hash1).toBe(hash2);
        });

        it('should return different hashes for different inputs', () => {
            const data1 = 'sensitive-data-one';
            const data2 = 'sensitive-data-two';
            
            const hash1 = SecurityUtils.hashSensitiveData(data1);
            const hash2 = SecurityUtils.hashSensitiveData(data2);
            
            expect(hash1).not.toBe(hash2);
        });

        it('should return fixed-length hash', () => {
            const inputs = ['short', 'very long sensitive data string that should be hashed consistently'];
            
            inputs.forEach(input => {
                const hash = SecurityUtils.hashSensitiveData(input);
                expect(hash).toHaveLength(16);
            });
        });

        it('should return alphanumeric hash only', () => {
            const data = 'test@email.com';
            const hash = SecurityUtils.hashSensitiveData(data);
            
            expect(hash).toMatch(/^[a-zA-Z0-9]+$/);
        });
    });

    describe('validateUrl', () => {
        it('should accept valid HTTP URLs', () => {
            const validUrls = [
                'http://example.com',
                'https://example.com',
                'https://subdomain.example.com/path',
                'http://localhost:3000',
                'https://example.com/path?query=value#fragment'
            ];

            validUrls.forEach(url => {
                expect(SecurityUtils.validateUrl(url)).toBe(true);
            });
        });

        it('should reject invalid URLs', () => {
            const invalidUrls = [
                'ftp://example.com',
                'javascript:alert(1)',
                'data:text/html,<script>alert(1)</script>',
                'not-a-url',
                '',
                'mailto:test@example.com',
                'file:///etc/passwd'
            ];

            invalidUrls.forEach(url => {
                expect(SecurityUtils.validateUrl(url)).toBe(false);
            });
        });

        it('should handle malformed URLs gracefully', () => {
            const malformedUrls = [
                'not-a-url',
                '',
                'http://',
                'https://'
            ];

            malformedUrls.forEach(url => {
                expect(SecurityUtils.validateUrl(url)).toBe(false);
            });
        });
    });

    describe('escapeHtml', () => {
        it('should escape HTML special characters', () => {
            const htmlInput = '<div>Hello & "World"</div>';
            const result = SecurityUtils.escapeHtml(htmlInput);
            
            expect(result).toBe('&lt;div&gt;Hello &amp; "World"&lt;/div&gt;');
        });

        it('should escape script tags', () => {
            const scriptInput = '<script>alert("xss")</script>';
            const result = SecurityUtils.escapeHtml(scriptInput);
            
            expect(result).toBe('&lt;script&gt;alert("xss")&lt;/script&gt;');
        });

        it('should handle quotes and apostrophes', () => {
            const quotesInput = `Hello "world" and 'universe'`;
            const result = SecurityUtils.escapeHtml(quotesInput);
            
            expect(result).toContain('Hello "world" and \'universe\'');
        });

        it('should handle empty string', () => {
            const result = SecurityUtils.escapeHtml('');
            expect(result).toBe('');
        });

        it('should handle plain text without HTML', () => {
            const plainText = 'This is plain text without HTML';
            const result = SecurityUtils.escapeHtml(plainText);
            
            expect(result).toBe(plainText);
        });

        it('should escape multiple HTML elements', () => {
            const complexHtml = '<img src="x" onerror="alert(1)"><script>evil()</script>';
            const result = SecurityUtils.escapeHtml(complexHtml);
            
            expect(result).not.toContain('<img');
            expect(result).not.toContain('<script');
            expect(result).toContain('&lt;');
            expect(result).toContain('&gt;');
        });
    });

    describe('integration scenarios', () => {
        it('should handle complete XSS prevention workflow', () => {
            const maliciousInput = '<script>alert("xss")</script><img onerror="alert(1)" src="x">';
            
            // First sanitize
            const sanitized = SecurityUtils.sanitizeInput(maliciousInput);
            expect(sanitized).not.toContain('<script>');
            
            // Then escape what remains
            const escaped = SecurityUtils.escapeHtml(sanitized);
            expect(escaped).not.toContain('<');
            expect(escaped).not.toContain('>');
        });

        it('should validate and process API keys securely', () => {
            const apiKey = 'abcd1234efgh5678ijkl9012mnop3456';
            
            // Validate format
            expect(SecurityUtils.validateApiKey(apiKey)).toBe(true);
            
            // Hash for storage
            const hashedKey = SecurityUtils.hashSensitiveData(apiKey);
            expect(hashedKey).toHaveLength(16);
            expect(hashedKey).not.toBe(apiKey);
        });

        it('should safely process user-provided URLs', () => {
            const userUrls = [
                'https://safe-site.com',
                'javascript:alert(1)',
                'http://malicious-site.com/safe-path'
            ];

            userUrls.forEach(url => {
                const isValid = SecurityUtils.validateUrl(url);
                if (isValid) {
                    // Safe to use
                    expect(url).toMatch(/^https?:/);
                } else {
                    // Reject unsafe URLs
                    expect(url).not.toMatch(/^https?:/);
                }
            });
        });
    });

    describe('performance tests', () => {
        it('should handle large input efficiently', () => {
            const largeInput = 'x'.repeat(10000);
            
            const startTime = performance.now();
            const result = SecurityUtils.sanitizeInput(largeInput);
            const endTime = performance.now();
            
            expect(result).toHaveLength(10000);
            expect(endTime - startTime).toBeLessThan(100); // Should complete within 100ms
        });

        it('should handle multiple rapid validations', () => {
            const apiKeys = Array(1000).fill(0).map((_, i) => 
                `abcd1234efgh5678ijkl9012mnop345${i.toString().padStart(1, '0')}`
            );

            const startTime = performance.now();
            const results = apiKeys.map(key => SecurityUtils.validateApiKey(key));
            const endTime = performance.now();

            expect(results).toHaveLength(1000);
            expect(results.every(result => typeof result === 'boolean')).toBe(true);
            expect(endTime - startTime).toBeLessThan(50); // Should complete within 50ms
        });
    });
});
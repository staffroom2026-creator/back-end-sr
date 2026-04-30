const Joi = require('joi');

// ─── Auth Validators ─────────────────────────────────────────────────────────

const registerTeacherSchema = Joi.object({
    full_name: Joi.string().min(2).max(255).required(),
    email: Joi.string().email().required(),
    phone: Joi.string().max(20).required(),
    password: Joi.string().min(6).max(128).required()
});

const registerSchoolSchema = Joi.object({
    school_name: Joi.string().min(2).max(255).required(),
    contact_person_name: Joi.string().min(2).max(255).required(),
    email: Joi.string().email().required(),
    phone: Joi.string().max(20).required(),
    password: Joi.string().min(6).max(128).required()
});

const loginSchema = Joi.object({
    email: Joi.string().email().required(),
    password: Joi.string().required(),
});

const changePasswordSchema = Joi.object({
    current_password: Joi.string().required(),
    new_password: Joi.string().min(6).max(128).required()
});

const forgotPasswordSchema = Joi.object({
    email: Joi.string().email().required()
});

const resetPasswordSchema = Joi.object({
    token: Joi.string().required(),
    new_password: Joi.string().min(6).max(128).required()
});


// ─── Profile Validators ──────────────────────────────────────────────────────

const teacherProfileSchema = Joi.object({
    phone: Joi.string().max(20).allow('', null),
    headline: Joi.string().max(255).allow('', null),
    bio: Joi.string().max(2000).allow('', null),
    skills: Joi.string().max(1000).allow('', null),
    experience_years: Joi.number().integer().min(0).max(50).allow(null),
    years_of_experience: Joi.number().integer().min(0).max(50).allow(null),
    qualification: Joi.string().max(255).allow('', null),
    subjects: Joi.alternatives().try(Joi.array().items(Joi.string()), Joi.string()).allow('', null),
    preferred_location: Joi.string().max(255).allow('', null),
    location: Joi.string().max(255).allow('', null), // fallback
    expected_salary: Joi.string().max(100).allow('', null),
    employment_type: Joi.string().valid('full-time', 'part-time', 'contract', 'temporary').allow('', null),
    availability_status: Joi.string().valid('available', 'not_looking', 'interviewing', 'hired').allow('', null),
    trcn_number: Joi.string().max(50).allow('', null),
});

const schoolProfileSchema = Joi.object({
    school_name: Joi.string().min(2).max(255).allow('', null),
    school_type: Joi.string().valid('nursery', 'primary', 'secondary', 'tertiary', 'vocational').allow(null),
    address: Joi.string().max(500).allow('', null),
    lga: Joi.string().max(100).allow('', null),
    state: Joi.string().max(100).allow('', null),
    website: Joi.string().uri().max(255).allow('', null),
});

// ─── Job Validators ──────────────────────────────────────────────────────────

const createJobSchema = Joi.object({
    title: Joi.string().min(3).max(255).required(),
    description: Joi.string().min(20).required(),
    role_type: Joi.string().max(100).allow('', null),
    employment_type: Joi.string().valid('full-time', 'part-time', 'contract', 'temporary').default('full-time'),
    salary_range: Joi.string().max(100).allow('', null),
    location: Joi.string().max(255).allow('', null),
    requirements: Joi.string().allow('', null),
});

const updateJobSchema = createJobSchema.keys({
    is_active: Joi.boolean(),
}).fork(
    ['title', 'description'],
    (schema) => schema.optional()
);

// ─── Application Validators ─────────────────────────────────────────────────

const applyJobSchema = Joi.object({
    cover_letter: Joi.string().max(3000).allow('', null),
});

const updateApplicationStatusSchema = Joi.object({
    status: Joi.string().valid('pending', 'shortlisted', 'interviewing', 'hired', 'rejected').required(),
});

// ─── Validation Middleware ───────────────────────────────────────────────────

/**
 * Generic Joi validation middleware.
 * Wraps a Joi schema and returns a middleware function.
 */
const validate = (schema) => {
    return (req, res, next) => {
        const { error, value } = schema.validate(req.body, {
            abortEarly: false,
            stripUnknown: true,
        });

        if (error) {
            const errors = error.details.map((detail) => ({
                field: detail.path.join('.'),
                message: detail.message.replace(/"/g, ''),
            }));

            return res.status(400).json({
                status: 'fail',
                message: 'Validation error',
                errors,
            });
        }

        req.body = value; // Use sanitized values
        next();
    };
};

module.exports = {
    registerTeacherSchema,
    registerSchoolSchema,
    loginSchema,
    changePasswordSchema,
    forgotPasswordSchema,
    resetPasswordSchema,
    teacherProfileSchema,
    schoolProfileSchema,
    createJobSchema,
    updateJobSchema,
    applyJobSchema,
    updateApplicationStatusSchema,
    validate,
};

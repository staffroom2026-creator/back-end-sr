const Joi = require('joi');

const createJobSchema = Joi.object({
    title: Joi.string().trim().required().max(255),
    subject: Joi.string().trim().required().max(100),
    description: Joi.string().required(),
    requirements: Joi.string().required(),
    responsibilities: Joi.string().allow('', null),
    location: Joi.string().trim().required().max(255),
    state: Joi.string().trim().required().max(100),
    salary_min: Joi.number().min(0).allow(null),
    salary_max: Joi.number().min(Joi.ref('salary_min')).allow(null),
    employment_type: Joi.string().valid('full-time', 'part-time', 'contract', 'temporary').default('full-time'),
    experience_level: Joi.string().valid('entry', 'mid', 'senior', 'lead').default('mid'),
    application_deadline: Joi.date().greater('now').allow(null)
});

const updateJobSchema = Joi.object({
    title: Joi.string().trim().max(255),
    subject: Joi.string().trim().max(100),
    description: Joi.string(),
    requirements: Joi.string(),
    responsibilities: Joi.string().allow('', null),
    location: Joi.string().trim().max(255),
    state: Joi.string().trim().max(100),
    salary_min: Joi.number().min(0).allow(null),
    salary_max: Joi.number().min(Joi.ref('salary_min')).allow(null),
    employment_type: Joi.string().valid('full-time', 'part-time', 'contract', 'temporary'),
    experience_level: Joi.string().valid('entry', 'mid', 'senior', 'lead'),
    application_deadline: Joi.date().greater('now').allow(null),
    status: Joi.string().valid('active', 'closed', 'draft')
}).min(1);

const filterJobsSchema = Joi.object({
    page: Joi.number().integer().min(1).default(1),
    limit: Joi.number().integer().min(1).max(100).default(10),
    search: Joi.string().trim().allow('', null),
    subject: Joi.string().trim().allow('', null),
    location: Joi.string().trim().allow('', null),
    state: Joi.string().trim().allow('', null),
    salary_min: Joi.number().min(0).allow(null),
    salary_max: Joi.number().min(0).allow(null),
    employment_type: Joi.string().trim().allow('', null),
    experience_level: Joi.string().trim().allow('', null),
    school_type: Joi.string().trim().allow('', null), // Joined from school_profiles
    featured: Joi.boolean().allow(null)
});

module.exports = {
    createJobSchema,
    updateJobSchema,
    filterJobsSchema
};

const Joi = require('joi');

const applySchema = Joi.object({
    job_id: Joi.number().integer().required(),
    cover_letter: Joi.string().required().min(50).max(5000),
    cv_url: Joi.string().uri().allow('', null) // Optional, can use profile CV
});

const updateStatusSchema = Joi.object({
    status: Joi.string().valid('reviewed', 'shortlisted', 'rejected', 'hired').required()
});

module.exports = {
    applySchema,
    updateStatusSchema
};
